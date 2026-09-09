<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\OpenCanService;
use App\Models\CanDefinition;
use App\Models\PlayerCan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenCanTest extends TestCase
{
    use RefreshDatabase;

    private function giveCan(User $user, int $quantity = 5): PlayerCan
    {
        return $user->cans()->create([
            'can_definition_id' => CanDefinition::firstWhere('slug', 'rusty')->id,
            'quantity' => $quantity,
        ]);
    }

    public function test_opening_requires_authentication(): void
    {
        $this->postJson('/api/cans/1/open')->assertUnauthorized();
    }

    public function test_opening_a_can_yields_ingredients_and_consumes_one_can(): void
    {
        $user = User::factory()->create();
        $can = $this->giveCan($user, 3);

        $response = $this->actingAs($user)->postJson("/api/cans/{$can->id}/open");

        $response->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonStructure(['openingId', 'seed', 'received' => [['slug', 'name', 'icon', 'quantity']]]);

        // 3 rolls from the rusty can
        $this->assertSame(3, collect($response->json('received'))->sum('quantity'));

        $this->assertSame(2, $can->fresh()->quantity);
        $this->assertDatabaseHas('can_openings', ['user_id' => $user->id, 'can_definition_id' => $can->can_definition_id]);
        $this->assertSame(3, $user->ingredients()->sum('quantity'));
    }

    public function test_same_idempotency_key_replays_without_opening_a_second_can(): void
    {
        $user = User::factory()->create();
        $can = $this->giveCan($user, 5);

        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'abc-123')
            ->postJson("/api/cans/{$can->id}/open")
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false');

        $second = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'abc-123')
            ->postJson("/api/cans/{$can->id}/open")
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(4, $can->fresh()->quantity); // only one consumed
        $this->assertSame(1, $user->canOpenings()->count());
    }

    public function test_a_different_idempotency_key_opens_another_can(): void
    {
        $user = User::factory()->create();
        $can = $this->giveCan($user, 5);

        $this->actingAs($user)->withHeader('Idempotency-Key', 'k1')->postJson("/api/cans/{$can->id}/open")->assertCreated();
        $this->actingAs($user)->withHeader('Idempotency-Key', 'k2')->postJson("/api/cans/{$can->id}/open")->assertCreated();

        $this->assertSame(3, $can->fresh()->quantity);
    }

    public function test_opening_without_a_key_opens_a_can_each_time(): void
    {
        $user = User::factory()->create();
        $can = $this->giveCan($user, 5);

        $this->actingAs($user)->postJson("/api/cans/{$can->id}/open")->assertCreated();
        $this->actingAs($user)->postJson("/api/cans/{$can->id}/open")->assertCreated();

        $this->assertSame(3, $can->fresh()->quantity);
    }

    public function test_cannot_open_a_can_you_do_not_own(): void
    {
        $owner = User::factory()->create();
        $can = $this->giveCan($owner, 2);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/cans/{$can->id}/open")
            ->assertNotFound();

        $this->assertSame(2, $can->fresh()->quantity);
    }

    public function test_cannot_open_an_empty_stack(): void
    {
        $user = User::factory()->create();
        $can = $this->giveCan($user, 0);

        $this->actingAs($user)
            ->postJson("/api/cans/{$can->id}/open")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nie masz tej puszki.');
    }

    public function test_opening_is_deterministic_for_a_fixed_seed(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $canA = $this->giveCan($userA, 1);
        $canB = $this->giveCan($userB, 1);

        $a = (new OpenCanService(forcedSeed: 777))->open($userA, $canA);
        $b = (new OpenCanService(forcedSeed: 777))->open($userB, $canB);

        $this->assertSame(
            collect($a->received)->pluck('slug')->all(),
            collect($b->received)->pluck('slug')->all(),
        );
    }
}
