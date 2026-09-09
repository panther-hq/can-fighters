<?php

namespace Tests\Feature\Fighters;

use App\Models\IngredientDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class MutateTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    private function giveIngredient(User $user, string $slug, int $qty): void
    {
        $user->ingredients()->create([
            'ingredient_definition_id' => IngredientDefinition::firstWhere('slug', $slug)->id,
            'quantity' => $qty,
        ]);
    }

    public function test_mutating_consumes_ingredients_and_records_the_mutation(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user, ['primary_class' => 'tank', 'traits' => ['metal', 'defense']]);
        $this->giveIngredient($user, 'fire', 2);

        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/mutate", [
                'ingredients' => [['slug' => 'fire', 'quantity' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $fighter->id)
            ->assertJsonStructure(['data' => ['traits', 'stats', 'skills']]);

        $this->assertSame(0, $user->ingredients()->sum('quantity'));
        $this->assertDatabaseHas('fighter_mutations', ['fighter_id' => $fighter->id]);
    }

    public function test_mutation_keeps_the_fighters_identity(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user, ['name' => 'Blaszany Stefan', 'level' => 4]);
        $this->giveIngredient($user, 'fire', 1);

        $this->actingAs($user)->postJson("/api/fighters/{$fighter->id}/mutate", [
            'ingredients' => [['slug' => 'fire', 'quantity' => 1]],
        ])->assertOk();

        $fresh = $fighter->fresh();
        $this->assertSame('Blaszany Stefan', $fresh->name);
        $this->assertSame(4, $fresh->level);
    }

    public function test_mutation_rejects_more_than_three_ingredients(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);
        $this->giveIngredient($user, 'fire', 5);

        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/mutate", [
                'ingredients' => [['slug' => 'fire', 'quantity' => 4]],
            ])
            ->assertStatus(422);

        $this->assertSame(5, $user->ingredients()->sum('quantity'));
    }

    public function test_cannot_mutate_someone_elses_fighter(): void
    {
        $owner = User::factory()->create();
        $fighter = $this->makeFighter($owner);

        $other = User::factory()->create();
        $this->giveIngredient($other, 'fire', 2);

        $this->actingAs($other)
            ->postJson("/api/fighters/{$fighter->id}/mutate", [
                'ingredients' => [['slug' => 'fire', 'quantity' => 1]],
            ])
            ->assertNotFound();
    }

    public function test_mutation_is_idempotent_per_key(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);
        $this->giveIngredient($user, 'fire', 3);

        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'mut-1')
            ->postJson("/api/fighters/{$fighter->id}/mutate", ['ingredients' => [['slug' => 'fire', 'quantity' => 1]]])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'mut-1')
            ->postJson("/api/fighters/{$fighter->id}/mutate", ['ingredients' => [['slug' => 'fire', 'quantity' => 1]]])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(2, $user->ingredients()->sum('quantity')); // only one mutation consumed
        $this->assertDatabaseCount('fighter_mutations', 1);
    }
}
