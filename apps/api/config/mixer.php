<?php

return [

    /*
    | Which creative provider generates fighter concepts.
    | fallback = deterministic, always available (spec §49 — game must work
    | without external AI). A real LLM provider can be added later.
    */
    'provider' => env('MIXER_PROVIDER', 'fallback'),

    'generation_version' => 1,

    'ingredients' => [
        'min' => 2,
        'max' => 6,
    ],

    // Allowed values the AI concept is validated / normalised against (spec §49).
    'classes' => [
        'tank', 'fighter', 'assassin', 'ranged', 'mage',
        'support', 'debuffer', 'summoner', 'engineer', 'crafter',
    ],

    'traits' => [
        'organic', 'flexible', 'speed', 'chaos', 'mechanical', 'armor',
        'electric', 'energy', 'support', 'debuff', 'control', 'weapon',
        'melee', 'crit', 'water', 'elemental', 'damage', 'poison',
        'metal', 'defense',
    ],

    'skill_families' => [
        'direct_damage', 'area_damage', 'heal', 'shield', 'taunt', 'stun',
        'slow', 'silence', 'poison', 'bleed', 'buff_attack', 'buff_defense',
        'buff_speed', 'debuff_attack', 'debuff_defense', 'summon', 'lifesteal',
        'counterattack', 'execute', 'cleanse', 'revive',
    ],

    'rarities' => ['common', 'uncommon', 'rare', 'epic', 'legendary'],

    // Dominant ingredient tag -> suggested class (fallback generator).
    'tag_class_map' => [
        'armor' => 'tank', 'defense' => 'tank', 'metal' => 'tank',
        'melee' => 'fighter', 'weapon' => 'fighter',
        'crit' => 'assassin', 'speed' => 'assassin', 'flexible' => 'assassin',
        'elemental' => 'mage', 'damage' => 'mage', 'electric' => 'mage', 'energy' => 'mage',
        'support' => 'support', 'organic' => 'support',
        'debuff' => 'debuffer', 'poison' => 'debuffer',
        'control' => 'engineer', 'mechanical' => 'engineer',
        'chaos' => 'summoner',
        'water' => 'ranged',
    ],

    // Class -> two default skill families (fallback generator).
    'class_skills' => [
        'tank' => ['taunt', 'shield'],
        'fighter' => ['direct_damage', 'bleed'],
        'assassin' => ['direct_damage', 'execute'],
        'ranged' => ['direct_damage', 'slow'],
        'mage' => ['area_damage', 'stun'],
        'support' => ['heal', 'buff_defense'],
        'debuffer' => ['poison', 'debuff_attack'],
        'summoner' => ['summon', 'buff_speed'],
        'engineer' => ['direct_damage', 'slow'],
        'crafter' => ['buff_defense', 'shield'],
    ],

    // Absurd Polish name pools (spec §4).
    'name_prefixes' => [
        'Admirał', 'Wielki', 'Legendarny', 'Zardzewiały', 'Elektryczny',
        'Śmierdzący', 'Kapitan', 'Doktor', 'Baron', 'Święty', 'Szalony', 'Blaszany',
    ],
    'name_suffixes' => ['', '', '', 'II', 'III', 'z Puszki', 'Wielki', 'Młodszy', 'Ostatni'],

    // Ingredient slug -> name core used when that ingredient dominates the mix.
    'name_cores' => [
        'pasta' => 'Makaroniarz',
        'screw' => 'Śrubotron',
        'battery' => 'Bateriusz',
        'cheese' => 'Serowy Mnich',
        'sock' => 'Skarpetnik',
        'magnet' => 'Magnetyk',
        'fork' => 'Widelczysko',
        'fish' => 'Rybogłów',
        'fire' => 'Ognik',
        'slime' => 'Maziak',
        'tin_can' => 'Blaszak',
        'spring' => 'Sprężyniec',
    ],

    'personalities' => [
        'chaotyczny', 'spokojny', 'wściekły', 'tajemniczy',
        'wesoły', 'ponury', 'dumny', 'tchórzliwy',
    ],
];
