<?php

return [
    // How many offers the daily shop rolls.
    'offer_count' => 5,

    'can' => [
        'slug' => 'rusty',
        'price' => 320,
        'chance' => 70, // % chance the daily shop includes a can offer
    ],

    'ingredient' => [
        'min_qty' => 2,
        'max_qty' => 4,
        'price_per_unit' => [
            'common' => 14,
            'uncommon' => 22,
            'rare' => 38,
            'epic' => 60,
            'legendary' => 90,
        ],
    ],

    'equipment' => [
        'chance' => 60,
        'rarities' => ['common', 'common', 'uncommon', 'rare'],
        'price' => ['common' => 220, 'uncommon' => 340, 'rare' => 520, 'epic' => 780, 'legendary' => 1100],
    ],
];
