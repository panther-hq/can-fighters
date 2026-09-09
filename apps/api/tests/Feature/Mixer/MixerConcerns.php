<?php

namespace Tests\Feature\Mixer;

use App\Models\IngredientDefinition;
use App\Models\User;

trait MixerConcerns
{
    /**
     * @param  array<string, int>  $ingredients  slug => quantity
     */
    protected function playerWithIngredients(array $ingredients): User
    {
        $user = User::factory()->create();

        foreach ($ingredients as $slug => $quantity) {
            $user->ingredients()->create([
                'ingredient_definition_id' => IngredientDefinition::firstWhere('slug', $slug)->id,
                'quantity' => $quantity,
            ]);
        }

        return $user;
    }
}
