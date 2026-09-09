<?php

namespace App\Domain\Fighters;

use App\Domain\Balance\StandardBalanceEngine;
use App\Domain\Mixer\CharacterGenerationInput;
use App\Domain\Mixer\IngredientSpec;
use App\Domain\Mixer\MixerService;
use App\Domain\Mixer\NotEnoughIngredientsException;
use App\Models\Fighter;
use App\Models\FighterMutation;
use App\Models\IngredientDefinition;
use App\Models\PlayerIngredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Re-mixes an existing fighter with 1–3 more ingredients (spec §19): shifts
 * traits, appearance, one skill and possibly unlocks a secondary class, then
 * re-runs the Balance Engine. Keeps the fighter's identity (id, name, level).
 * Bounded — it does not create unlimited power scaling.
 */
class MutateFighter
{
    public const MIN = 1;

    public const MAX = 3;

    public function __construct(
        private MixerService $mixer,
        private StandardBalanceEngine $balance,
    ) {}

    /**
     * @param  list<array{slug: string, quantity: int}>  $input
     */
    public function __invoke(User $user, Fighter $fighter, array $input): Fighter
    {
        $total = array_sum(array_map(fn ($row) => (int) $row['quantity'], $input));
        if ($total < self::MIN || $total > self::MAX) {
            throw ValidationException::withMessages([
                'ingredients' => 'Do mutacji użyj od 1 do 3 składników.',
            ]);
        }

        return DB::transaction(function () use ($user, $fighter, $input): Fighter {
            $specs = $this->consume($user, $input);

            $seed = random_int(1, PHP_INT_MAX);
            $fighterSpec = new IngredientSpec(
                slug: 'self',
                name: $fighter->name,
                quantity: max(2, (int) ceil(count($specs) * 1.5)),
                tags: $fighter->traits,
                rarity: $fighter->rarity,
            );

            $concept = $this->mixer->generateConcept(
                new CharacterGenerationInput([...$specs, $fighterSpec], $seed),
            );

            $before = $this->snapshot($fighter);

            $newTraits = array_values(array_slice(
                array_unique([...$fighter->traits, ...$concept->traits]),
                0,
                3,
            ));

            $fighter->update([
                'traits' => $newTraits,
                'visual_dna' => $concept->visualDna,
                'secondary_class' => $fighter->secondary_class ?? $concept->secondaryClass,
            ]);

            // Replace the second skill (slot 1) with the mutation's flavour.
            if (isset($concept->suggestedSkills[1])) {
                $fighter->skills()->updateOrCreate(
                    ['slot' => 1],
                    [
                        'skill_family' => $concept->suggestedSkills[1]['skillFamily'],
                        'modifier' => $concept->suggestedSkills[1]['modifier'] ?? null,
                        'parameters' => [],
                    ],
                );
            }

            $this->balance->apply($fighter->fresh(['skills']));

            FighterMutation::create([
                'fighter_id' => $fighter->id,
                'seed' => $seed,
                'input' => array_values($input),
                'before' => $before,
                'after' => $this->snapshot($fighter->fresh(['stats', 'skills'])),
            ]);

            return $fighter->fresh(['stats', 'skills']);
        });
    }

    /**
     * @param  list<array{slug: string, quantity: int}>  $input
     * @return list<IngredientSpec>
     */
    private function consume(User $user, array $input): array
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

            $playerIngredient = PlayerIngredient::query()
                ->where('user_id', $user->id)
                ->where('ingredient_definition_id', $definition->id)
                ->lockForUpdate()
                ->first();

            if ($playerIngredient === null || $playerIngredient->quantity < (int) $row['quantity']) {
                throw new NotEnoughIngredientsException;
            }

            $playerIngredient->decrement('quantity', (int) $row['quantity']);

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
     * @return array<string, mixed>
     */
    private function snapshot(Fighter $fighter): array
    {
        return [
            'traits' => $fighter->traits,
            'visualDna' => $fighter->visual_dna,
            'secondaryClass' => $fighter->secondary_class,
            'stats' => $fighter->stats?->only(['hp', 'attack', 'defense', 'magic', 'speed', 'crit', 'power_score']),
            'skills' => $fighter->skills->map->only(['slot', 'skill_family', 'modifier'])->all(),
        ];
    }
}
