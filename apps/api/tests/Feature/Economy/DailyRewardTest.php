<?php

namespace Tests\Feature\Economy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyRewardTest extends TestCase
{
    use RefreshDatabase;

    private function player(int $coins = 0): User
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => $coins]);

        return $user;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/daily')->assertUnauthorized();
        $this->postJson('/api/daily/claim')->assertUnauthorized();
    }

    public function test_a_fresh_player_can_claim_day_one(): void
    {
        $user = $this->player();

        $this->actingAs($user)->getJson('/api/daily')
            ->assertOk()
            ->assertJsonPath('canClaim', true)
            ->assertJsonPath('day', 1);

        $this->actingAs($user)->postJson('/api/daily/claim')
            ->assertOk()
            ->assertJsonPath('streak', 1)
            ->assertJsonPath('reward.type', 'coins')
            ->assertJsonPath('reward.amount', 60);

        $this->assertSame(60, $user->playerProfile->fresh()->coins);

        $this->actingAs($user)->postJson('/api/daily/claim')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nagrodę już dziś odebrano.');
    }

    public function test_claiming_on_consecutive_days_walks_the_ladder(): void
    {
        $user = $this->player();

        $this->actingAs($user)->postJson('/api/daily/claim')->assertJsonPath('streak', 1);

        $this->travel(1)->day();
        $this->actingAs($user)->postJson('/api/daily/claim')
            ->assertJsonPath('streak', 2)
            ->assertJsonPath('day', 2);
    }

    public function test_a_missed_day_resets_the_streak(): void
    {
        $user = $this->player();
        $this->actingAs($user)->postJson('/api/daily/claim')->assertJsonPath('streak', 1);

        $this->travel(3)->days();

        $this->actingAs($user)->getJson('/api/daily')->assertJsonPath('streak', 0)->assertJsonPath('day', 1);
        $this->actingAs($user)->postJson('/api/daily/claim')->assertJsonPath('streak', 1);
    }

    public function test_day_seven_grants_a_can(): void
    {
        $user = $this->player();

        for ($day = 0; $day < 6; $day++) {
            $this->actingAs($user)->postJson('/api/daily/claim')->assertOk();
            $this->travel(1)->day();
        }

        $this->actingAs($user)->postJson('/api/daily/claim')
            ->assertOk()
            ->assertJsonPath('day', 7)
            ->assertJsonPath('reward.type', 'can');

        $this->assertGreaterThan(0, $user->cans()->sum('quantity'));
    }
}
