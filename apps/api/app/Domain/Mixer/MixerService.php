<?php

namespace App\Domain\Mixer;

use App\Events\MixerCompleted;
use App\Events\MixerFailed;
use App\Jobs\ProcessMixRequest;
use App\Models\Fighter;
use App\Models\IngredientDefinition;
use App\Models\MixRequest;
use App\Models\PlayerIngredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Orchestrates the Mixer flow (spec §8–§9): resolve + consume ingredients,
 * ask the creative provider for a concept, validate it, persist the fighter,
 * announce completion. Numbers come later (Balance Engine, phase 5).
 */
class MixerService
{
    public function __construct(private ConceptValidator $validator) {}

    /**
     * Generate a concept without consuming anything (spec §55 mixer/preview).
     *
     * @param  list<array{slug: string, quantity: int}>  $input
     */
    public function preview(User $user, array $input): CharacterConcept
    {
        $this->assertIngredientCount($input);
        $specs = $this->buildSpecs($input);
        $this->assertOwnership($user, $input);

        $generationInput = new CharacterGenerationInput($specs, random_int(1, PHP_INT_MAX));

        return $this->validator->validate($this->generate($generationInput));
    }

    /**
     * Consume ingredients, record the request, queue generation (spec §47).
     *
     * @param  list<array{slug: string, quantity: int}>  $input
     */
    public function requestMix(User $user, array $input): MixRequest
    {
        $this->assertIngredientCount($input);
        $this->buildSpecs($input); // validates slugs before we touch anything

        return DB::transaction(function () use ($user, $input): MixRequest {
            $this->consume($user, $input);

            $mix = MixRequest::create([
                'user_id' => $user->id,
                'seed' => random_int(1, PHP_INT_MAX),
                'status' => MixRequest::STATUS_PROCESSING,
                'generation_version' => (int) config('mixer.generation_version'),
                'input' => array_values($input),
            ]);

            ProcessMixRequest::dispatch($mix->id)->afterCommit();

            return $mix;
        });
    }

    /**
     * Run generation for a queued request (called from the job, and inline on
     * the sync queue).
     */
    public function process(MixRequest $mix): void
    {
        if ($mix->status !== MixRequest::STATUS_PROCESSING) {
            return;
        }

        try {
            $specs = $this->buildSpecs($mix->input);
            $generationInput = new CharacterGenerationInput($specs, $mix->seed);
            $concept = $this->validator->validate($this->generate($generationInput));

            $fighter = $this->persistFighter($mix->user, $concept, $mix->seed, $mix->generation_version);

            $mix->update([
                'status' => MixRequest::STATUS_COMPLETED,
                'result_fighter_id' => $fighter->id,
                'completed_at' => now(),
            ]);

            $this->announce(new MixerCompleted($mix->fresh(['resultFighter', 'user'])));
        } catch (Throwable $e) {
            report($e);

            $mix->update([
                'status' => MixRequest::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 250),
                'completed_at' => now(),
            ]);

            $this->announce(new MixerFailed($mix->fresh()));
        }
    }

    /**
     * Broadcasting is best-effort: a socket problem must never turn a saved
     * result into a failure (spec §70 — DB is the source of truth).
     */
    private function announce(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Configured provider, with one retry, then the deterministic fallback
     * (spec §49) — generation never hard-fails the game.
     */
    private function generate(CharacterGenerationInput $input): CharacterConcept
    {
        $provider = $this->resolveProvider();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $provider->generate($input);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return app(FallbackCharacterGenerationProvider::class)->generate($input);
    }

    private function resolveProvider(): CharacterGenerationProvider
    {
        return match (config('mixer.provider')) {
            'mock' => app(MockCharacterGenerationProvider::class),
            default => app(FallbackCharacterGenerationProvider::class),
        };
    }

    private function persistFighter(User $user, CharacterConcept $concept, int $seed, int $version): Fighter
    {
        return $user->fighters()->create([
            'name' => $concept->name,
            'description' => $concept->description,
            'primary_class' => $concept->primaryClass,
            'secondary_class' => $concept->secondaryClass,
            'rarity' => $concept->rarity,
            'personality' => $concept->personality,
            'traits' => $concept->traits,
            'visual_dna' => $concept->visualDna,
            'suggested_skills' => $concept->suggestedSkills,
            'generation_seed' => $seed,
            'generation_version' => $version,
        ]);
    }

    /**
     * @param  list<array{slug: string, quantity: int}>  $input
     */
    private function assertIngredientCount(array $input): void
    {
        $total = array_sum(array_map(fn ($row) => (int) $row['quantity'], $input));
        $min = (int) config('mixer.ingredients.min');
        $max = (int) config('mixer.ingredients.max');

        if ($total < $min || $total > $max) {
            throw ValidationException::withMessages([
                'ingredients' => "Wrzuć od {$min} do {$max} składników.",
            ]);
        }
    }

    /**
     * @param  list<array{slug: string, quantity: int}>  $input
     * @return list<IngredientSpec>
     */
    private function buildSpecs(array $input): array
    {
        $definitions = IngredientDefinition::query()
            ->whereIn('slug', array_column($input, 'slug'))
            ->get()
            ->keyBy('slug');

        $specs = [];
        foreach ($input as $row) {
            $definition = $definitions->get($row['slug']);
            if ($definition === null) {
                throw ValidationException::withMessages([
                    'ingredients' => "Nieznany składnik: {$row['slug']}.",
                ]);
            }

            $specs[] = new IngredientSpec(
                slug: $definition->slug,
                name: $definition->name,
                quantity: (int) $row['quantity'],
                tags: $definition->tags,
                rarity: $definition->rarity,
            );
        }

        return $specs;
    }

    /**
     * @param  list<array{slug: string, quantity: int}>  $input
     */
    private function assertOwnership(User $user, array $input): void
    {
        $owned = $user->ingredients()
            ->with('definition')
            ->get()
            ->keyBy(fn (PlayerIngredient $row) => $row->definition->slug);

        foreach ($input as $row) {
            $have = $owned->get($row['slug'])?->quantity ?? 0;
            if ($have < (int) $row['quantity']) {
                throw new NotEnoughIngredientsException;
            }
        }
    }

    /**
     * @param  list<array{slug: string, quantity: int}>  $input
     */
    private function consume(User $user, array $input): void
    {
        foreach ($input as $row) {
            $playerIngredient = PlayerIngredient::query()
                ->where('user_id', $user->id)
                ->whereHas('definition', fn ($q) => $q->where('slug', $row['slug']))
                ->lockForUpdate()
                ->first();

            if ($playerIngredient === null || $playerIngredient->quantity < (int) $row['quantity']) {
                throw new NotEnoughIngredientsException;
            }

            $playerIngredient->decrement('quantity', (int) $row['quantity']);
        }
    }
}
