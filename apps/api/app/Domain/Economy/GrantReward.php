<?php

namespace App\Domain\Economy;

use App\Domain\Equipment\EquipmentRoller;
use App\Models\CanDefinition;
use App\Models\EquipmentDefinition;
use App\Models\IngredientDefinition;
use App\Models\User;
use App\Support\SeededRng;

/**
 * Single place that hands a reward to a player — coins, xp, ingredients, cans
 * or a rolled equipment piece. Used by the daily reward, the shop and the
 * region-map loot / event nodes. Returns a UI-friendly description.
 */
class GrantReward
{
    public function __construct(private EquipmentRoller $equipmentRoller) {}

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function grant(User $user, array $spec, ?SeededRng $rng = null): array
    {
        $rng ??= new SeededRng(random_int(1, PHP_INT_MAX));

        return match ($spec['type'] ?? '') {
            'coins' => $this->addCoins($user, (int) $spec['amount']),
            'xp' => $this->addXp($user, (int) $spec['amount']),
            'ingredient' => $this->addIngredient($user, $spec['slug'], (int) ($spec['qty'] ?? 1)),
            'ingredients' => $this->addRandomIngredients($user, (int) $spec['picks'], (int) ($spec['each'] ?? 1), $rng),
            'can' => $this->addCan($user, $spec['slug'] ?? 'rusty', (int) ($spec['qty'] ?? 1)),
            'equipment' => $this->addEquipment($user, $spec['slug'] ?? null, $spec['rarity'] ?? 'common', $rng),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function addCoins(User $user, int $amount): array
    {
        $user->playerProfile()->increment('coins', $amount);

        return ['type' => 'coins', 'amount' => $amount];
    }

    /**
     * @return array<string, mixed>
     */
    private function addXp(User $user, int $amount): array
    {
        $user->playerProfile()->increment('xp', $amount);

        return ['type' => 'xp', 'amount' => $amount];
    }

    /**
     * @return array<string, mixed>
     */
    private function addIngredient(User $user, string $slug, int $qty): array
    {
        $definition = IngredientDefinition::firstWhere('slug', $slug);
        if ($definition === null) {
            return [];
        }
        $user->ingredients()
            ->firstOrCreate(['ingredient_definition_id' => $definition->id], ['quantity' => 0])
            ->increment('quantity', $qty);

        return ['type' => 'ingredient', 'slug' => $slug, 'name' => $definition->name, 'icon' => $definition->icon, 'quantity' => $qty];
    }

    /**
     * @return array<string, mixed>
     */
    private function addRandomIngredients(User $user, int $picks, int $each, SeededRng $rng): array
    {
        $slugs = IngredientDefinition::query()->where('rarity', 'common')->pluck('slug')->all();
        $items = [];
        for ($i = 0; $i < $picks && $slugs !== []; $i++) {
            $items[] = $this->addIngredient($user, $rng->pick($slugs), $each);
        }

        return ['type' => 'ingredients', 'items' => array_values(array_filter($items))];
    }

    /**
     * @return array<string, mixed>
     */
    private function addCan(User $user, string $slug, int $qty): array
    {
        $definition = CanDefinition::firstWhere('slug', $slug);
        if ($definition === null) {
            return [];
        }
        $user->cans()
            ->firstOrCreate(['can_definition_id' => $definition->id], ['quantity' => 0])
            ->increment('quantity', $qty);

        return ['type' => 'can', 'slug' => $slug, 'name' => $definition->name, 'icon' => $definition->icon, 'quantity' => $qty];
    }

    /**
     * @return array<string, mixed>
     */
    private function addEquipment(User $user, ?string $slug, string $rarity, SeededRng $rng): array
    {
        $definition = $slug !== null
            ? EquipmentDefinition::firstWhere('slug', $slug)
            : EquipmentDefinition::query()->inRandomOrder()->first();
        if ($definition === null) {
            return [];
        }

        $piece = $this->equipmentRoller->roll($user, $definition, $rarity, $rng->next());

        return [
            'type' => 'equipment',
            'id' => $piece->id,
            'slug' => $definition->slug,
            'name' => $definition->name,
            'icon' => $definition->icon,
            'slot' => $definition->slot,
            'rarity' => $piece->rarity,
        ];
    }
}
