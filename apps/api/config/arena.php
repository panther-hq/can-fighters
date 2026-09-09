<?php

return [

    'starting_rating' => 1000,
    'min_rating' => 100,
    'k_factor' => 32,

    // rating floor => Polish league name (spec §30)
    'leagues' => [
        0 => 'Brąz',
        1000 => 'Srebro',
        1150 => 'Złoto',
        1300 => 'Platyna',
        1450 => 'Diament',
        1650 => 'Mistrz',
        1850 => 'Puszkowa Legenda',
    ],

    // opponents are drawn from within this rating band, widening if too few
    'opponent_band' => 150,
    'opponent_count' => 6,
];
