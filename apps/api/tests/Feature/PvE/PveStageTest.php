<?php

namespace Tests\Feature\PvE;

use App\Models\Battle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PveStageTest extends TestCase
{
    use PveConcerns, RefreshDatabase;

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/pve/stages')->assertUnauthorized();
        $this->postJson('/api/pve/stages/kitchen-1/battle')->assertUnauthorized();
    }

    public function test_stage_list_shows_the_first_stage_unlocked_and_the_rest_locked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/pve/stages')->assertOk();

        $response->assertJsonCount(6, 'stages')
            ->assertJsonPath('stages.0.slug', 'kitchen-1')
            ->assertJsonPath('stages.0.unlocked', true)
            ->assertJsonPath('stages.1.unlocked', false)
            ->assertJsonPath('stages.5.isBoss', true);
    }

    public function test_a_strong_team_clears_a_stage_and_earns_rewards(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 10, 'primary_class' => 'fighter']);

        $response = $this->actingAs($user)
            ->postJson('/api/pve/stages/kitchen-1/battle')
            ->assertOk()
            ->assertJsonPath('won', true)
            ->assertJsonStructure(['battleId', 'stars', 'rewards' => ['coins', 'xp'], 'result' => ['winner', 'events']]);

        $this->assertGreaterThanOrEqual(1, $response->json('stars'));
        $this->assertSame(45, $user->playerProfile->fresh()->coins);

        $this->assertDatabaseHas('battles', ['player_a_id' => $user->id, 'stage_slug' => 'kitchen-1', 'winner' => 'A']);
        $this->assertDatabaseHas('player_stage_progress', ['user_id' => $user->id, 'stage_slug' => 'kitchen-1']);
        $this->assertSame(2, Battle::firstOrFail()->snapshots()->count());
    }

    public function test_clearing_a_stage_unlocks_the_next_one(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 10]);

        $this->actingAs($user)->postJson('/api/pve/stages/kitchen-1/battle')->assertOk();

        $this->actingAs($user)->getJson('/api/pve/stages')
            ->assertJsonPath('stages.1.unlocked', true);
    }

    public function test_clearing_a_stage_grants_fighter_xp(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 10]);

        $this->actingAs($user)->postJson('/api/pve/stages/kitchen-1/battle')->assertOk();

        $this->assertGreaterThan(0, $user->fighters()->sum('xp') + $user->fighters()->sum('level') - 30);
    }

    public function test_a_hopeless_team_loses_and_gets_nothing(): void
    {
        $user = $this->playerWithTeam(1);
        $user->fighters()->first()->stats->update(['hp' => 8, 'attack' => 1, 'defense' => 0, 'power_score' => 3]);

        $this->actingAs($user)
            ->postJson('/api/pve/stages/kitchen-1/battle')
            ->assertOk()
            ->assertJsonPath('won', false)
            ->assertJsonPath('stars', 0);

        $this->assertSame(0, $user->playerProfile->fresh()->coins);
        $this->assertDatabaseCount('player_stage_progress', 0);
        $this->assertDatabaseHas('battles', ['stage_slug' => 'kitchen-1', 'winner' => 'B']);
    }

    public function test_a_locked_stage_cannot_be_fought(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 10]);

        $this->actingAs($user)
            ->postJson('/api/pve/stages/kitchen-3/battle')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Ten etap jest jeszcze zablokowany.');
    }

    public function test_fighting_without_a_team_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create([]);

        $this->actingAs($user)
            ->postJson('/api/pve/stages/kitchen-1/battle')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Najpierw ustaw drużynę.');
    }

    public function test_a_retried_battle_does_not_double_reward(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 10]);

        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'pve-1')
            ->postJson('/api/pve/stages/kitchen-1/battle')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');

        $second = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'pve-1')
            ->postJson('/api/pve/stages/kitchen-1/battle')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('battleId'), $second->json('battleId'));
        $this->assertSame(45, $user->playerProfile->fresh()->coins);
        $this->assertDatabaseCount('battles', 1);
    }

    public function test_the_boss_drops_equipment(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 12]);
        foreach (['kitchen-1', 'kitchen-2', 'kitchen-3', 'kitchen-4', 'kitchen-5'] as $slug) {
            $user->stageProgress()->create(['stage_slug' => $slug, 'stars' => 3, 'cleared_at' => now()]);
        }

        $response = $this->actingAs($user)
            ->postJson('/api/pve/stages/kitchen-boss/battle')
            ->assertOk()
            ->assertJsonPath('won', true);

        $this->assertNotEmpty($response->json('rewards.equipment'));
        $this->assertDatabaseHas('player_equipment', ['user_id' => $user->id]);
    }

    public function test_a_stored_battle_can_be_replayed(): void
    {
        $user = $this->playerWithTeam(3, ['rarity' => 'legendary', 'level' => 10]);
        $battleId = $this->actingAs($user)->postJson('/api/pve/stages/kitchen-1/battle')->json('battleId');

        $this->actingAs($user)->getJson("/api/pve/battles/{$battleId}")
            ->assertOk()
            ->assertJsonPath('battleId', $battleId)
            ->assertJsonPath('winner', 'A')
            ->assertJsonStructure(['seed', 'result' => ['events', 'winner', 'survivors']]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/pve/battles/{$battleId}")
            ->assertNotFound();
    }
}
