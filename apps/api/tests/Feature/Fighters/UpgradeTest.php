<?php

namespace Tests\Feature\Fighters;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class UpgradeTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    public function test_upgrade_requires_authentication(): void
    {
        $this->postJson('/api/fighters/1/upgrade')->assertUnauthorized();
    }

    public function test_upgrading_spends_coins_and_raises_level_and_stats(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => 500]);
        $fighter = $this->makeFighter($user, ['level' => 1]);
        $powerBefore = $fighter->stats->power_score;

        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/upgrade")
            ->assertOk()
            ->assertJsonPath('data.level', 2);

        $this->assertSame(400, $user->playerProfile->fresh()->coins); // cost = level(1) * 100
        $this->assertGreaterThan($powerBefore, $fighter->fresh()->stats->power_score);
    }

    public function test_upgrading_without_enough_coins_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => 50]);
        $fighter = $this->makeFighter($user);

        $this->actingAs($user)
            ->postJson("/api/fighters/{$fighter->id}/upgrade")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Za mało monet.');

        $this->assertSame(50, $user->playerProfile->fresh()->coins);
        $this->assertSame(1, $fighter->fresh()->level);
    }

    public function test_cannot_upgrade_someone_elses_fighter(): void
    {
        $owner = User::factory()->create();
        $owner->playerProfile()->create(['coins' => 500]);
        $fighter = $this->makeFighter($owner);

        $other = User::factory()->create();
        $other->playerProfile()->create(['coins' => 500]);

        $this->actingAs($other)
            ->postJson("/api/fighters/{$fighter->id}/upgrade")
            ->assertNotFound();
    }
}
