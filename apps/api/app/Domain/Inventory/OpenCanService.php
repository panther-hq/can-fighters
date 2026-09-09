<?php

namespace App\Domain\Inventory;

use App\Models\CanOpening;
use App\Models\IngredientDefinition;
use App\Models\PlayerCan;
use App\Models\PlayerIngredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opens one can from a player's stack: rolls its drop table, moves the
 * ingredients into the player's inventory and records the opening.
 * Atomic (spec §57); deterministic given a seed (spec §11).
 */
class OpenCanService
{
    public function __construct(private ?int $forcedSeed = null) {}

    public function open(User $user, PlayerCan $playerCan): CanOpeningResult
    {
        return DB::transaction(function () use ($user, $playerCan): CanOpeningResult {
            /** @var PlayerCan $locked */
            $locked = PlayerCan::query()->whereKey($playerCan->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->quantity < 1) {
                throw new NotEnoughCansException;
            }

            $can = $locked->definition;
            $seed = $this->forcedSeed ?? random_int(1, PHP_INT_MAX);

            $slugs = (new WeightedRoller($can->weights(), $seed))->roll($can->rolls());
            /** @var array<string, int> $counts */
            $counts = array_count_values($slugs);

            $locked->decrement('quantity');

            $definitions = IngredientDefinition::query()
                ->whereIn('slug', array_keys($counts))
                ->get()
                ->keyBy('slug');

            $received = [];
            foreach ($counts as $slug => $quantity) {
                /** @var IngredientDefinition $definition */
                $definition = $definitions[$slug];

                $row = PlayerIngredient::query()->firstOrCreate(
                    ['user_id' => $user->id, 'ingredient_definition_id' => $definition->id],
                    ['quantity' => 0],
                );
                $row->increment('quantity', $quantity);

                $received[] = [
                    'slug' => $slug,
                    'name' => $definition->name,
                    'icon' => $definition->icon,
                    'quantity' => $quantity,
                ];
            }

            $opening = CanOpening::create([
                'user_id' => $user->id,
                'can_definition_id' => $can->id,
                'seed' => $seed,
                'results' => $received,
            ]);

            return new CanOpeningResult($opening->id, $seed, $received);
        });
    }
}
