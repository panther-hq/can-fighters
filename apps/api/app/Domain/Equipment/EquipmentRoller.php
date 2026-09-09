<?php

namespace App\Domain\Equipment;

use App\Models\EquipmentDefinition;
use App\Models\PlayerEquipment;
use App\Models\User;
use App\Support\SeededRng;

/**
 * Rolls a concrete piece from a definition: base stats × rarity multiplier ×
 * a small seeded variance. Deterministic given the seed (spec §24, §35).
 */
class EquipmentRoller
{
    private const RARITY_MULTIPLIER = [
        'common' => 1.0,
        'uncommon' => 1.18,
        'rare' => 1.4,
        'epic' => 1.7,
        'legendary' => 2.1,
    ];

    public function roll(User $user, EquipmentDefinition $definition, string $rarity, ?int $seed = null): PlayerEquipment
    {
        $seed ??= random_int(1, PHP_INT_MAX);
        $rng = new SeededRng($seed);

        $multiplier = self::RARITY_MULTIPLIER[$rarity] ?? 1.0;
        $rolled = [];
        foreach ($definition->base_stats as $stat => $value) {
            $variance = 0.9 + $rng->int(21) / 100; // 0.90 .. 1.10
            $rolled[$stat] = max(1, (int) round($value * $multiplier * $variance));
        }

        return $user->equipment()->create([
            'equipment_definition_id' => $definition->id,
            'rarity' => $rarity,
            'seed' => $seed,
            'rolled_stats' => $rolled,
        ]);
    }
}
