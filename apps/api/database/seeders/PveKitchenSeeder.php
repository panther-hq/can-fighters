<?php

namespace Database\Seeders;

use App\Models\PveStageDefinition;
use Illuminate\Database\Seeder;

/**
 * The Kitchen region (spec §6). Idempotent (keyed on slug).
 */
class PveKitchenSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->stages() as $stage) {
            PveStageDefinition::updateOrCreate(['slug' => $stage['slug']], $stage);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(): array
    {
        $spoon = fn (string $pos = 'front') => ['name' => 'Wściekła Łyżka', 'class' => 'fighter', 'position' => $pos];
        $fork = fn (string $pos = 'middle') => ['name' => 'Rozjuszony Widelec', 'class' => 'assassin', 'position' => $pos];
        $slime = fn (string $pos = 'front') => ['name' => 'Serowy Szlam', 'class' => 'tank', 'position' => $pos];
        $fly = fn (string $pos = 'back') => ['name' => 'Kuchenna Mucha', 'class' => 'ranged', 'position' => $pos];

        return [
            $this->stage('kitchen-1', 1, 'Kuchenny Bałagan', 68, [$spoon('front'), $fly('back')], ['coins' => 45, 'xp' => 20, 'fighterXp' => 15]),
            $this->stage('kitchen-2', 2, 'Szuflada z Widelcami', 76, [$spoon('front'), $fork('middle'), $fork('back')], ['coins' => 60, 'xp' => 26, 'fighterXp' => 18]),
            $this->stage('kitchen-3', 3, 'Serowa Kałuża', 85, [$slime('front'), $fork('middle'), $fly('back')], ['coins' => 75, 'xp' => 32, 'fighterXp' => 22, 'canDrops' => [['slug' => 'rusty', 'chance' => 20]], 'equipmentDrops' => [['slug' => 'cardboard-vest', 'chance' => 25, 'rarity' => 'common']]]),
            $this->stage('kitchen-4', 4, 'Nocna Zmiana', 94, [$slime('front'), $spoon('middle'), $fork('back')], ['coins' => 90, 'xp' => 38, 'fighterXp' => 26, 'canDrops' => [['slug' => 'rusty', 'chance' => 30]], 'equipmentDrops' => [['slug' => 'sock-mace', 'chance' => 30, 'rarity' => 'uncommon']]]),
            $this->stage('kitchen-5', 5, 'Gorący Piec', 104, [$slime('front'), $fork('middle'), $fly('back')], ['coins' => 105, 'xp' => 45, 'fighterXp' => 30, 'canDrops' => [['slug' => 'rusty', 'chance' => 40]], 'equipmentDrops' => [['slug' => 'grandma-lid', 'chance' => 40, 'rarity' => 'uncommon']]]),
            $this->stage('kitchen-boss', 6, 'Szef Patelnia', 132, [
                ['name' => 'Szef Patelnia', 'class' => 'tank', 'position' => 'front'],
                $fork('middle'),
                $fly('back'),
            ], ['coins' => 160, 'xp' => 70, 'fighterXp' => 45, 'canDrops' => [['slug' => 'rusty', 'chance' => 100]], 'equipmentDrops' => [['slug' => 'stefan-fork', 'chance' => 100, 'rarity' => 'rare']]], isBoss: true),
        ];
    }

    /**
     * @param  list<array{name: string, class: string, position: string}>  $enemies
     * @param  array<string, mixed>  $rewards
     * @return array<string, mixed>
     */
    private function stage(string $slug, int $order, string $name, int $budget, array $enemies, array $rewards, bool $isBoss = false): array
    {
        return [
            'slug' => $slug,
            'region' => 'kitchen',
            'name' => $name,
            'order' => $order,
            'is_boss' => $isBoss,
            'enemy_budget' => $budget,
            'enemies' => $enemies,
            'rewards' => array_merge([
                'coins' => 40,
                'xp' => 20,
                'fighterXp' => 15,
                'ingredientDrops' => [
                    ['slug' => 'pasta', 'chance' => 45, 'min' => 1, 'max' => 2],
                    ['slug' => 'screw', 'chance' => 45, 'min' => 1, 'max' => 2],
                    ['slug' => 'cheese', 'chance' => 35, 'min' => 1, 'max' => 1],
                    ['slug' => 'fork', 'chance' => 20, 'min' => 1, 'max' => 1],
                ],
                'canDrops' => [],
                'equipmentDrops' => [],
            ], $rewards),
        ];
    }
}
