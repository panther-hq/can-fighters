<?php

/*
| Login-streak reward ladder (7 days, then repeats). Day 7 is a can, so a
| week of logins guarantees a mix.
*/

return [
    'ladder' => [
        1 => ['type' => 'coins', 'amount' => 60],
        2 => ['type' => 'coins', 'amount' => 110],
        3 => ['type' => 'ingredients', 'picks' => 2, 'each' => 2],
        4 => ['type' => 'coins', 'amount' => 170],
        5 => ['type' => 'ingredient', 'slug' => 'fire', 'qty' => 1],
        6 => ['type' => 'coins', 'amount' => 240],
        7 => ['type' => 'can', 'slug' => 'rusty', 'qty' => 1],
    ],
];
