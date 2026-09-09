<?php

/*
| Balance Engine inputs (spec §15–§17). Every number a fighter fights with is
| derived here — never by the AI. Tuned by feel; adjust freely, it is covered
| by tests that assert relative shape (tanks tanky, assassins fast), not exact
| values.
*/

return [

    'base_power_budget' => 100,

    'rarity_budget_bonus' => [
        'common' => 0,
        'uncommon' => 8,
        'rare' => 15,
        'epic' => 24,
        'legendary' => 35,
    ],

    // Compounding stat growth per level above 1.
    'level_growth' => 0.09,

    // Class weight profiles — how the budget is spread across stats (spec §16).
    'profiles' => [
        'tank' => ['hp' => 2.4, 'attack' => 0.7, 'defense' => 1.8, 'magic' => 0.4, 'speed' => 0.7, 'crit' => 0.2],
        'fighter' => ['hp' => 1.5, 'attack' => 1.7, 'defense' => 1.0, 'magic' => 0.3, 'speed' => 1.0, 'crit' => 0.6],
        'assassin' => ['hp' => 0.9, 'attack' => 1.8, 'defense' => 0.5, 'magic' => 0.3, 'speed' => 2.0, 'crit' => 1.4],
        'ranged' => ['hp' => 1.0, 'attack' => 1.7, 'defense' => 0.6, 'magic' => 0.6, 'speed' => 1.3, 'crit' => 0.9],
        'mage' => ['hp' => 0.9, 'attack' => 0.4, 'defense' => 0.5, 'magic' => 2.2, 'speed' => 1.0, 'crit' => 0.7],
        'support' => ['hp' => 1.3, 'attack' => 0.5, 'defense' => 1.0, 'magic' => 1.6, 'speed' => 1.1, 'crit' => 0.3],
        'debuffer' => ['hp' => 1.1, 'attack' => 0.7, 'defense' => 0.8, 'magic' => 1.7, 'speed' => 1.2, 'crit' => 0.4],
        'summoner' => ['hp' => 1.2, 'attack' => 0.6, 'defense' => 0.8, 'magic' => 1.8, 'speed' => 1.0, 'crit' => 0.3],
        'engineer' => ['hp' => 1.3, 'attack' => 1.1, 'defense' => 1.1, 'magic' => 1.0, 'speed' => 0.9, 'crit' => 0.5],
        'crafter' => ['hp' => 1.2, 'attack' => 0.6, 'defense' => 1.1, 'magic' => 0.9, 'speed' => 0.9, 'crit' => 0.3],
    ],

    // weight (at budget 100) -> stat value
    'scale' => ['hp' => 28, 'attack' => 7, 'defense' => 7, 'magic' => 7, 'speed' => 7, 'crit' => 3],
    'floor' => ['hp' => 40, 'attack' => 4, 'defense' => 3, 'magic' => 2, 'speed' => 4, 'crit' => 1],
    'crit_cap' => 60,

    // power_score = Σ stat × weight (spec §16)
    'power_weights' => ['hp' => 0.30, 'attack' => 1.6, 'defense' => 1.4, 'magic' => 1.5, 'speed' => 1.2, 'crit' => 1.0],

    // A secondary class blends its profile in at this ratio.
    'secondary_blend' => 0.35,

    // Per-family skill parameter baselines.
    'skill_defaults' => [
        'direct_damage' => ['power' => 18, 'cooldown' => 2],
        'area_damage' => ['power' => 12, 'cooldown' => 3, 'targets' => 3],
        'heal' => ['power' => 16, 'cooldown' => 3],
        'shield' => ['power' => 20, 'cooldown' => 4, 'duration' => 2],
        'taunt' => ['duration' => 2, 'cooldown' => 4],
        'stun' => ['chance' => 25, 'duration' => 1, 'cooldown' => 4],
        'slow' => ['amount' => 25, 'duration' => 2, 'cooldown' => 3],
        'silence' => ['duration' => 1, 'cooldown' => 5],
        'poison' => ['power' => 6, 'duration' => 3, 'cooldown' => 3],
        'bleed' => ['power' => 7, 'duration' => 3, 'cooldown' => 3],
        'buff_attack' => ['amount' => 20, 'duration' => 2, 'cooldown' => 4],
        'buff_defense' => ['amount' => 20, 'duration' => 2, 'cooldown' => 4],
        'buff_speed' => ['amount' => 20, 'duration' => 2, 'cooldown' => 4],
        'debuff_attack' => ['amount' => 20, 'duration' => 2, 'cooldown' => 4],
        'debuff_defense' => ['amount' => 20, 'duration' => 2, 'cooldown' => 4],
        'summon' => ['power' => 10, 'cooldown' => 5],
        'lifesteal' => ['power' => 14, 'ratio' => 40, 'cooldown' => 3],
        'counterattack' => ['power' => 12, 'chance' => 30, 'cooldown' => 0],
        'execute' => ['power' => 16, 'threshold' => 25, 'cooldown' => 4],
        'cleanse' => ['cooldown' => 4],
        'revive' => ['power' => 40, 'cooldown' => 8],
    ],

    'skill_power_growth' => 0.12,
];
