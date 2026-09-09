<?php

namespace Database\Seeders;

use App\Models\CanDefinition;
use App\Models\EquipmentDefinition;
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

        foreach ($this->equipment() as $item) {
            EquipmentDefinition::updateOrCreate(['slug' => $item['slug']], $item);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function equipment(): array
    {
        return [
            // weapons
            ['slug' => 'stefan-fork', 'name' => 'Widelec Stefana', 'icon' => '🍴', 'slot' => 'weapon', 'rarity' => 'common', 'base_stats' => ['attack' => 12, 'crit' => 4], 'special' => 'Przebicie'],
            ['slug' => 'tin-sword', 'name' => 'Blaszany Miecz', 'icon' => '🗡️', 'slot' => 'weapon', 'rarity' => 'common', 'base_stats' => ['attack' => 14, 'defense' => 3]],
            ['slug' => 'electro-beater', 'name' => 'Elektryczny Trzepak', 'icon' => '⚡', 'slot' => 'weapon', 'rarity' => 'common', 'base_stats' => ['magic' => 13, 'speed' => 3]],
            ['slug' => 'sock-mace', 'name' => 'Skarpeta na Kiju', 'icon' => '🧦', 'slot' => 'weapon', 'rarity' => 'common', 'base_stats' => ['attack' => 9, 'speed' => 4]],
            ['slug' => 'soup-ladle', 'name' => 'Wielka Chochla', 'icon' => '🥄', 'slot' => 'weapon', 'rarity' => 'common', 'base_stats' => ['attack' => 10, 'hp' => 18]],

            // armor
            ['slug' => 'can-armor', 'name' => 'Pancerz z Puszki', 'icon' => '🥫', 'slot' => 'armor', 'rarity' => 'common', 'base_stats' => ['hp' => 42, 'defense' => 12]],
            ['slug' => 'grandma-lid', 'name' => 'Pokrywka Babci', 'icon' => '🛡️', 'slot' => 'armor', 'rarity' => 'common', 'base_stats' => ['defense' => 14, 'hp' => 12]],
            ['slug' => 'cardboard-vest', 'name' => 'Karton Ochronny', 'icon' => '📦', 'slot' => 'armor', 'rarity' => 'common', 'base_stats' => ['hp' => 36, 'defense' => 6]],
            ['slug' => 'bubble-wrap', 'name' => 'Folia Bąbelkowa', 'icon' => '🫧', 'slot' => 'armor', 'rarity' => 'common', 'base_stats' => ['hp' => 24, 'defense' => 10]],
            ['slug' => 'chef-apron', 'name' => 'Fartuch Szefa', 'icon' => '🥼', 'slot' => 'armor', 'rarity' => 'common', 'base_stats' => ['defense' => 9, 'magic' => 8]],

            // accessories
            ['slug' => 'lucky-magnet', 'name' => 'Magnes Szczęścia', 'icon' => '🧲', 'slot' => 'accessory', 'rarity' => 'common', 'base_stats' => ['crit' => 6, 'speed' => 3]],
            ['slug' => 'shaman-sock', 'name' => 'Śmierdząca Skarpeta Szamana', 'icon' => '🧦', 'slot' => 'accessory', 'rarity' => 'common', 'base_stats' => ['magic' => 10, 'speed' => 2], 'special' => 'Moc Osłabień'],
            ['slug' => 'spare-battery', 'name' => 'Bateria Zapasowa', 'icon' => '🔋', 'slot' => 'accessory', 'rarity' => 'common', 'base_stats' => ['speed' => 5, 'magic' => 5]],
            ['slug' => 'spring-boots', 'name' => 'Sprężynowe Buty', 'icon' => '🌀', 'slot' => 'accessory', 'rarity' => 'common', 'base_stats' => ['speed' => 7]],
            ['slug' => 'coffee-mug', 'name' => 'Kubek Kawy', 'icon' => '☕', 'slot' => 'accessory', 'rarity' => 'common', 'base_stats' => ['speed' => 4, 'crit' => 3]],
        ];
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
