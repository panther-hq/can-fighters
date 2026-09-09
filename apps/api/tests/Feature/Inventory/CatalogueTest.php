<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingredients_require_authentication(): void
    {
        $this->getJson('/api/ingredients')->assertUnauthorized();
    }

    public function test_ingredients_returns_the_seeded_catalogue(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/ingredients')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('data.0.slug', 'pasta')
            ->assertJsonStructure([
                'data' => [['slug', 'name', 'icon', 'rarity', 'powerValue', 'tags']],
            ]);
    }

    public function test_inventory_requires_authentication(): void
    {
        $this->getJson('/api/player/inventory')->assertUnauthorized();
    }

    public function test_inventory_returns_ingredients_and_cans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/player/inventory')
            ->assertOk()
            ->assertJsonStructure(['ingredients', 'cans']);
    }
}
