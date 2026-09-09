<?php

namespace Database\Seeders;

use App\Models\CanDefinition;
use App\Models\IngredientDefinition;
use Illuminate\Database\Seeder;

/**
 * Static game content — ingredients and cans (spec §7, §65).
 * Idempotent: keyed on `slug`, safe to run on every deploy.
 */
class GameContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->ingredients() as $ingredient) {
            IngredientDefinition::updateOrCreate(
                ['slug' => $ingredient['slug']],
                $ingredient,
            );
        }

        foreach ($this->cans() as $can) {
            CanDefinition::updateOrCreate(['slug' => $can['slug']], $can);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ingredients(): array
    {
        return [
            ['slug' => 'pasta', 'name' => 'Makaron', 'icon' => '🍝', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['organic', 'flexible', 'speed', 'chaos']],
            ['slug' => 'screw', 'name' => 'Śruba', 'icon' => '🔩', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['mechanical', 'armor']],
            ['slug' => 'battery', 'name' => 'Bateria', 'icon' => '🔋', 'rarity' => 'uncommon', 'power_value' => 12, 'tags' => ['electric', 'energy']],
            ['slug' => 'cheese', 'name' => 'Ser', 'icon' => '🧀', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['organic', 'support']],
            ['slug' => 'sock', 'name' => 'Skarpeta', 'icon' => '🧦', 'rarity' => 'common', 'power_value' => 9, 'tags' => ['chaos', 'debuff']],
            ['slug' => 'magnet', 'name' => 'Magnes', 'icon' => '🧲', 'rarity' => 'uncommon', 'power_value' => 12, 'tags' => ['mechanical', 'control']],
            ['slug' => 'fork', 'name' => 'Widelec', 'icon' => '🍴', 'rarity' => 'uncommon', 'power_value' => 13, 'tags' => ['weapon', 'melee', 'crit']],
            ['slug' => 'fish', 'name' => 'Ryba', 'icon' => '🐟', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['organic', 'water']],
            ['slug' => 'fire', 'name' => 'Ogień', 'icon' => '🔥', 'rarity' => 'rare', 'power_value' => 16, 'tags' => ['elemental', 'damage']],
            ['slug' => 'slime', 'name' => 'Maź', 'icon' => '🟢', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['poison', 'control']],
            ['slug' => 'tin_can', 'name' => 'Blaszana puszka', 'icon' => '🥫', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['metal', 'defense']],
            ['slug' => 'spring', 'name' => 'Sprężyna', 'icon' => '🌀', 'rarity' => 'common', 'power_value' => 10, 'tags' => ['mechanical', 'speed']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cans(): array
    {
        return [
            [
                'slug' => 'rusty',
                'name' => 'Zardzewiała puszka',
                'icon' => '🥫',
                'rarity' => 'common',
                'drops' => [
                    'rolls' => 3,
                    'weights' => [
                        'pasta' => 14,
                        'screw' => 14,
                        'tin_can' => 12,
                        'spring' => 12,
                        'cheese' => 11,
                        'sock' => 11,
                        'fish' => 10,
                        'slime' => 10,
                        'battery' => 7,
                        'magnet' => 7,
                        'fork' => 5,
                        'fire' => 2,
                    ],
                ],
            ],
        ];
    }
}
