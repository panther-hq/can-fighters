<?php

namespace Tests\Feature\Mixer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewTest extends TestCase
{
    use MixerConcerns, RefreshDatabase;

    public function test_preview_requires_authentication(): void
    {
        $this->postJson('/api/mixer/preview', ['ingredients' => []])->assertUnauthorized();
    }

    public function test_preview_returns_a_concept_without_consuming_ingredients(): void
    {
        $user = $this->playerWithIngredients(['battery' => 2, 'pasta' => 2]);

        $this->actingAs($user)
            ->postJson('/api/mixer/preview', [
                'ingredients' => [
                    ['slug' => 'battery', 'quantity' => 2],
                    ['slug' => 'pasta', 'quantity' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonStructure([
                'concept' => ['name', 'description', 'primaryClass', 'rarity', 'traits', 'suggestedSkills'],
            ]);

        $this->assertSame(2, $user->ingredients()->where('quantity', 2)->count());
    }

    public function test_preview_rejects_too_few_ingredients(): void
    {
        $user = $this->playerWithIngredients(['battery' => 1]);

        $this->actingAs($user)
            ->postJson('/api/mixer/preview', ['ingredients' => [['slug' => 'battery', 'quantity' => 1]]])
            ->assertStatus(422)
            ->assertJsonPath('errors.ingredients.0', 'Wrzuć od 2 do 6 składników.');
    }

    public function test_preview_rejects_an_unknown_ingredient(): void
    {
        $user = $this->playerWithIngredients(['battery' => 2]);

        $this->actingAs($user)
            ->postJson('/api/mixer/preview', [
                'ingredients' => [
                    ['slug' => 'battery', 'quantity' => 1],
                    ['slug' => 'unobtainium', 'quantity' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_preview_rejects_ingredients_the_player_does_not_own(): void
    {
        $user = $this->playerWithIngredients(['battery' => 1]);

        $this->actingAs($user)
            ->postJson('/api/mixer/preview', [
                'ingredients' => [['slug' => 'battery', 'quantity' => 3]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nie masz wystarczających składników.');
    }
}
