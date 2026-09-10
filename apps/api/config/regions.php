<?php

/*
| Region exploration (spec §61, Heroes-3 style). A run is a tile map you walk
| a hero across: fog of war, a movement-point budget per day, roaming enemies,
| treasure piles, "?" event tiles, and a boss guarding the region's completion.
*/

return [
    'overworld' => [
        'width' => 12,
        'height' => 12,
        'movement_per_day' => 6,     // tiles the hero can step per day
        'reveal_radius' => 2,        // Chebyshev radius uncovered around the hero
        'obstacle_density' => 15,    // % of tiles seeded as impassable rock
        'water_density' => 5,        // % seeded as impassable water
        'objects' => [
            'enemies' => 5,
            'treasures' => 4,
            'events' => 3,
        ],
        'tier_step' => 2,                // hero-distance tiles per difficulty tier
        'max_tier' => 5,
        'budget_step_per_tier' => 9,     // enemy budget grows with distance from start
        'elite_chance' => 22,            // % of roaming enemies that are elites
        'elite_budget_multiplier' => 1.35,
        'boss_tier' => 6,
        'boss_budget_multiplier' => 1.9,
    ],

    // Base rewards for a plain battle at tier 0; scaled by tier + kind (RunBattle).
    'battle_rewards' => [
        'coins' => 30,
        'xp' => 16,
        'fighter_xp' => 12,
        'coins_per_row' => 12,
    ],

    'event_weights' => [
        'skarb' => 4,      // coins + chance of a can
        'trening' => 3,    // fighter xp
        'handlarz' => 2,   // a wandering merchant with one cheap rare offer
        'pulapka' => 2,    // lose coins or a small ambush
    ],
];
