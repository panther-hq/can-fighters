<?php

namespace Database\Seeders;

use App\Models\RegionDefinition;
use Illuminate\Database\Seeder;

/**
 * The four regions of the world (spec §6). Idempotent (keyed on slug).
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->regions() as $region) {
            RegionDefinition::updateOrCreate(['slug' => $region['slug']], $region);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function regions(): array
    {
        return [
            [
                'slug' => 'kitchen',
                'name' => 'Kuchnia',
                'order' => 1,
                'enemy_budget' => 66,
                'enemy_pool' => [
                    ['name' => 'Wściekła Łyżka', 'class' => 'fighter'],
                    ['name' => 'Rozjuszony Widelec', 'class' => 'assassin'],
                    ['name' => 'Serowy Szlam', 'class' => 'tank'],
                    ['name' => 'Kuchenna Mucha', 'class' => 'ranged'],
                ],
                'boss' => ['name' => 'Szef Patelnia', 'class' => 'tank'],
                'drops' => [
                    'ingredients' => ['pasta', 'screw', 'cheese', 'fork'],
                    'cans' => ['rusty'],
                    'equipment' => ['soup-ladle', 'cardboard-vest', 'stefan-fork'],
                ],
            ],
            [
                'slug' => 'garage',
                'name' => 'Garaż',
                'order' => 2,
                'enemy_budget' => 92,
                'enemy_pool' => [
                    ['name' => 'Śrubling', 'class' => 'fighter'],
                    ['name' => 'Nakrętkowy Potwór', 'class' => 'tank'],
                    ['name' => 'Rdzeniak', 'class' => 'ranged'],
                    ['name' => 'Wojownik Klucz', 'class' => 'assassin'],
                    ['name' => 'Iskrownik', 'class' => 'mage'],
                ],
                'boss' => ['name' => 'Mega Klucz', 'class' => 'fighter'],
                'drops' => [
                    'ingredients' => ['screw', 'battery', 'magnet', 'spring', 'fork'],
                    'cans' => ['rusty'],
                    'equipment' => ['tin-sword', 'electro-beater', 'can-armor', 'spare-battery', 'lucky-magnet'],
                ],
            ],
            [
                'slug' => 'trash',
                'name' => 'Śmietnik',
                'order' => 3,
                'enemy_budget' => 120,
                'enemy_pool' => [
                    ['name' => 'Kartonowa Bestia', 'class' => 'tank'],
                    ['name' => 'Bananowy Zombie', 'class' => 'fighter'],
                    ['name' => 'Puszkożerca', 'class' => 'assassin'],
                    ['name' => 'Plastikowy Potwór', 'class' => 'debuffer'],
                ],
                'boss' => ['name' => 'Król Śmieci', 'class' => 'tank'],
                'drops' => [
                    'ingredients' => ['sock', 'slime', 'fish', 'tin_can', 'cheese'],
                    'cans' => ['rusty'],
                    'equipment' => ['bubble-wrap', 'grandma-lid', 'sock-mace', 'coffee-mug'],
                ],
            ],
            [
                'slug' => 'lab',
                'name' => 'Laboratorium',
                'order' => 4,
                'enemy_budget' => 150,
                'enemy_pool' => [
                    ['name' => 'Baterion', 'class' => 'mage'],
                    ['name' => 'Magnetor', 'class' => 'engineer'],
                    ['name' => 'Laserowy Szczur', 'class' => 'ranged'],
                    ['name' => 'Eksperymentalny Szlam', 'class' => 'debuffer'],
                ],
                'boss' => ['name' => 'Doktor Mikser', 'class' => 'mage'],
                'drops' => [
                    'ingredients' => ['battery', 'fire', 'slime', 'magnet', 'spring'],
                    'cans' => ['rusty'],
                    'equipment' => ['electro-beater', 'shaman-sock', 'chef-apron', 'spring-boots'],
                ],
            ],
        ];
    }
}
