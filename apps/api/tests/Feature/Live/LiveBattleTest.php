<?php

namespace Tests\Feature\Live;

use App\Models\LiveBattle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class LiveBattleTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $fighterAttributes
     */
    private function player(array $fighterAttributes = []): User
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['rating' => 1000]);
        $team = $user->teams()->create(['type' => 'campaign']);

        foreach (Team::POSITIONS as $i => $position) {
            $fighter = $this->makeFighter($user, $fighterAttributes);
            $team->members()->create(['fighter_id' => $fighter->id, 'position' => $position]);
        }

        return $user;
    }

    /**
     * @return array{0: User, 1: User, 2: LiveBattle}
     */
    private function matchedPair(array $aAttrs = [], array $bAttrs = []): array
    {
        $a = $this->player($aAttrs);
        $b = $this->player($bAttrs);

        $this->actingAs($a)->postJson('/api/arena/live/queue')->assertOk()->assertJsonPath('status', 'queued');
        $matched = $this->actingAs($b)->postJson('/api/arena/live/queue')->assertOk()->assertJsonPath('status', 'matched');

        return [$a, $b, LiveBattle::findOrFail($matched->json('battleId'))];
    }

    public function test_queue_requires_a_team(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create([]);

        $this->actingAs($user)->postJson('/api/arena/live/queue')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Najpierw ustaw drużynę.');
    }

    public function test_two_players_get_matched_into_a_battle(): void
    {
        [$a, $b, $battle] = $this->matchedPair();

        $this->assertSame('active', $battle->status);
        $this->assertCount(6, $battle->state['units']);

        $this->actingAs($a)->getJson("/api/battles/{$battle->id}")
            ->assertOk()
            ->assertJsonPath('battle.yourTeam', 'A')
            ->assertJsonPath('battle.round', 1);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/battles/{$battle->id}")
            ->assertNotFound();
    }

    public function test_a_player_can_leave_the_queue(): void
    {
        $a = $this->player();
        $this->actingAs($a)->postJson('/api/arena/live/queue')->assertJsonPath('status', 'queued');
        $this->actingAs($a)->deleteJson('/api/arena/live/queue')->assertNoContent();

        // rejoining still just queues (nobody waiting)
        $this->actingAs($a)->postJson('/api/arena/live/queue')->assertJsonPath('status', 'queued');
    }

    public function test_a_round_resolves_once_both_players_act(): void
    {
        [$a, $b, $battle] = $this->matchedPair();
        $unitA = collect($battle->state['units'])->firstWhere('team', 'A');
        $unitB = collect($battle->state['units'])->firstWhere('team', 'B');

        $this->actingAs($a)->postJson("/api/battles/{$battle->id}/actions", [
            'actorId' => $unitA['id'], 'type' => 'attack', 'targetId' => $unitB['id'],
        ])->assertOk()->assertJsonPath('status', 'waiting');

        $this->actingAs($b)->postJson("/api/battles/{$battle->id}/actions", [
            'actorId' => $unitB['id'], 'type' => 'attack', 'targetId' => $unitA['id'],
        ])->assertOk()->assertJsonPath('status', 'resolved')
            ->assertJsonPath('battle.round', 2);

        $this->assertSame(2, $battle->fresh()->round);
        $this->assertNotEmpty($battle->fresh()->pending === []);
    }

    public function test_action_validation(): void
    {
        [$a, $b, $battle] = $this->matchedPair();
        $enemyId = collect($battle->state['units'])->firstWhere('team', 'B')['id'];

        // actor must be your own alive fighter
        $this->actingAs($a)->postJson("/api/battles/{$battle->id}/actions", [
            'actorId' => $enemyId, 'type' => 'attack', 'targetId' => $enemyId,
        ])->assertStatus(422)->assertJsonPath('message', 'Wybierz swojego żywego wojownika.');

        // not a participant
        $stranger = $this->player();
        $mineId = collect($battle->state['units'])->firstWhere('team', 'A')['id'];
        $this->actingAs($stranger)->postJson("/api/battles/{$battle->id}/actions", [
            'actorId' => $mineId, 'type' => 'attack',
        ])->assertNotFound();
    }

    public function test_a_timeout_poll_auto_resolves_the_round(): void
    {
        [$a, $b, $battle] = $this->matchedPair();

        $this->actingAs($a)->postJson("/api/battles/{$battle->id}/resolve")
            ->assertOk()->assertJsonPath('status', 'waiting');

        $this->travel((int) config('live.round_timeout_seconds') + 2)->seconds();

        $this->actingAs($b)->postJson("/api/battles/{$battle->id}/resolve")
            ->assertOk()
            ->assertJsonPath('status', fn ($s) => in_array($s, ['resolved', 'finished'], true));

        $this->assertGreaterThan(1, $battle->fresh()->round);
    }

    public function test_a_battle_can_be_fought_to_a_finish_and_updates_ratings(): void
    {
        [$a, $b, $battle] = $this->matchedPair(
            ['rarity' => 'legendary', 'level' => 15],
            ['rarity' => 'common', 'level' => 1],
        );

        for ($round = 0; $round < 40 && $battle->fresh()->status === 'active'; $round++) {
            $fresh = $battle->fresh();
            foreach ([[$a, 'A'], [$b, 'B']] as [$user, $side]) {
                $mine = collect($fresh->state['units'])->first(fn ($u) => $u['team'] === $side && $u['hp'] > 0);
                $enemy = collect($fresh->state['units'])->first(fn ($u) => $u['team'] !== $side && $u['hp'] > 0);
                if ($mine && $enemy) {
                    $this->actingAs($user)->postJson("/api/battles/{$fresh->id}/actions", [
                        'actorId' => $mine['id'], 'type' => 'attack', 'targetId' => $enemy['id'],
                    ]);
                }
            }
        }

        $done = $battle->fresh();
        $this->assertSame('finished', $done->status);
        $this->assertSame('A', $done->winner);
        $this->assertGreaterThan(1000, $a->playerProfile->fresh()->rating);
        $this->assertLessThan(1000, $b->playerProfile->fresh()->rating);
    }
}
