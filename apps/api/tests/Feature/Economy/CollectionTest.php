<?php

namespace Tests\Feature\Economy;

use App\Models\IngredientDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/collection')->assertUnauthorized();
    }

    public function test_it_reports_progress(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['rating' => 1200]);
        $this->makeFighter($user);
        $user->ingredients()->create([
            'ingredient_definition_id' => IngredientDefinition::first()->id,
            'quantity' => 3,
        ]);
        $user->regionClears()->create(['region_slug' => 'kitchen', 'times_cleared' => 2, 'first_cleared_at' => now()]);

        $this->actingAs($user)->getJson('/api/collection')
            ->assertOk()
            ->assertJsonPath('ingredientsFound', 1)
            ->assertJsonPath('ingredientsTotal', 12)
            ->assertJsonPath('fighters', 1)
            ->assertJsonPath('regionsCleared', 1)
            ->assertJsonPath('regionsTotal', 4)
            ->assertJsonPath('league', 'Złoto');
    }
}
