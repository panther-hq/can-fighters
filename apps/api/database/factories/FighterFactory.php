<?php

namespace Database\Factories;

use App\Models\Fighter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fighter>
 */
class FighterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Blaszany Wojownik',
            'description' => 'Testowy wojownik.',
            'primary_class' => 'fighter',
            'secondary_class' => null,
            'rarity' => 'common',
            'personality' => 'spokojny',
            'level' => 1,
            'xp' => 0,
            'traits' => ['mechanical', 'metal'],
            'visual_dna' => ['body' => 'tin_can', 'accent' => 'metal', 'effect' => 'mechanical'],
            'suggested_skills' => [
                ['skillFamily' => 'direct_damage', 'modifier' => null],
                ['skillFamily' => 'shield', 'modifier' => null],
            ],
            'generation_seed' => 123456,
            'generation_version' => 1,
        ];
    }
}
