<?php

/*
| Roguelike region-map generation. A run is a branching graph of nodes you
| route through from the bottom row to the boss on top.
*/

return [
    'choice_rows' => 5,          // + 1 boss row on top
    'nodes_per_row' => [2, 3, 3, 4],
    'budget_step_per_row' => 9,  // enemy budget grows as you climb
    'elite_budget_multiplier' => 1.35,
    'boss_budget_multiplier' => 1.9,

    // Node-type weights per choice-row index (0 = bottom).
    'row_weights' => [
        0 => ['battle' => 6, 'loot' => 2, 'event' => 1],
        1 => ['battle' => 4, 'loot' => 2, 'event' => 3, 'merchant' => 1],
        2 => ['battle' => 3, 'event' => 3, 'merchant' => 2, 'elite' => 2],
        3 => ['battle' => 2, 'event' => 3, 'merchant' => 2, 'elite' => 3],
        4 => ['battle' => 2, 'event' => 2, 'elite' => 4, 'loot' => 1],
    ],

    // Base rewards for a plain battle node at row 0; scaled by row + node kind.
    'battle_rewards' => [
        'coins' => 30,
        'xp' => 16,
        'fighter_xp' => 12,
        'coins_per_row' => 12,
    ],

    'event_weights' => [
        'skarb' => 4,      // coins + chance of a can
        'trening' => 3,    // fighter xp
        'handlarz' => 2,   // one cheap rare merchant offer
        'pulapka' => 2,    // lose coins or a small ambush
    ],
];
