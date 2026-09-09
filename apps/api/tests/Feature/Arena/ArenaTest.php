<?php

namespace Tests\Feature\Arena;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class ArenaTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $fighterAttributes
     */
    private function playerWith(string $teamType, int $rating = 1000, array $fighterAttributes = []): User
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['rating' => $rating]);
        $team = $user->teams()->create(['type' => $teamType]);

        foreach (Team::POSITIONS as $i => $position) {
            $fighter = $this->makeFighter($user, $fighterAttributes);
            $team->members()->create(['fighter_id' => $fighter->id, 'position' => $position]);
        }

        return $user;
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/arena')->assertUnauthorized();
        $this->getJson('/api/arena/opponents')->assertUnauthorized();
    }

    public function test_a_player_can_set_a_defense_team(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create([]);
        $fighter = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/arena/defense-team', [
            'members' => [['fighterId' => $fighter->id, 'position' => 'front']],
        ])->assertOk()->assertJsonPath('team.members.0.fighter.id', $fighter->id);

        $this->assertDatabaseHas('teams', ['user_id' => $user->id, 'type' => 'defense']);
    }

    public function test_arena_summary_reports_rating_and_league(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['rating' => 1200]);

        $this->actingAs($user)->getJson('/api/arena')
            ->assertOk()
            ->assertJsonPath('rating', 1200)
            ->assertJsonPath('league', 'Złoto')
            ->assertJsonPath('defenseTeam', null);
    }

    public function test_opponents_only_lists_players_with_a_defense_team(): void
    {
        $me = $this->playerWith('campaign');
        $withDefense = $this->playerWith('defense', 1010);
        $withoutDefense = User::factory()->create();
        $withoutDefense->playerProfile()->create(['rating' => 1005]);

        $this->actingAs($me)->getJson('/api/arena/opponents')
            ->assertOk()
            ->assertJsonCount(1, 'opponents')
            ->assertJsonPath('opponents.0.id', $withDefense->id)
            ->assertJsonPath('opponents.0.league', 'Srebro')
            ->assertJsonPath('opponents.0.teamPower', fn ($p) => $p > 0);
    }

    public function test_a_strong_attacker_beats_the_defender_and_gains_rating(): void
    {
        $attacker = $this->playerWith('campaign', 1000, ['rarity' => 'legendary', 'level' => 12]);
        $defender = $this->playerWith('defense', 1000, ['rarity' => 'common', 'level' => 1]);

        $response = $this->actingAs($attacker)
            ->postJson("/api/arena/challenge/{$defender->id}")
            ->assertOk()
            ->assertJsonPath('won', true)
            ->assertJsonStructure(['battleId', 'rating' => ['before', 'after', 'delta'], 'result' => ['events', 'winner']]);

        $this->assertGreaterThan(0, $response->json('rating.delta'));
        $this->assertSame($response->json('rating.after'), $attacker->playerProfile->fresh()->rating);
        $this->assertLessThan(1000, $defender->playerProfile->fresh()->rating);

        $this->assertDatabaseHas('battles', ['type' => 'arena', 'player_a_id' => $attacker->id, 'player_b_id' => $defender->id]);
        $this->assertDatabaseHas('arena_results', ['attacker_id' => $attacker->id, 'defender_id' => $defender->id, 'attacker_won' => true]);
    }

    public function test_cannot_challenge_yourself(): void
    {
        $me = $this->playerWith('campaign');
        $me->teams()->create(['type' => 'defense'])->members()->create([
            'fighter_id' => $this->makeFighter($me)->id,
            'position' => 'front',
        ]);

        $this->actingAs($me)
            ->postJson("/api/arena/challenge/{$me->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nie możesz wyzwać samego siebie.');
    }

    public function test_cannot_challenge_a_player_without_a_defense_team(): void
    {
        $attacker = $this->playerWith('campaign');
        $defender = User::factory()->create();
        $defender->playerProfile()->create([]);

        $this->actingAs($attacker)
            ->postJson("/api/arena/challenge/{$defender->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ten gracz nie ma drużyny obronnej.');
    }

    public function test_challenge_is_idempotent_per_key(): void
    {
        $attacker = $this->playerWith('campaign', 1000, ['rarity' => 'legendary', 'level' => 12]);
        $defender = $this->playerWith('defense', 1000);

        $first = $this->actingAs($attacker)
            ->withHeader('Idempotency-Key', 'arena-1')
            ->postJson("/api/arena/challenge/{$defender->id}")
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');

        $this->actingAs($attacker)
            ->withHeader('Idempotency-Key', 'arena-1')
            ->postJson("/api/arena/challenge/{$defender->id}")
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('arena_results', 1);
        $this->assertSame($first->json('rating.after'), $attacker->playerProfile->fresh()->rating);
    }

    public function test_ranking_and_history(): void
    {
        $attacker = $this->playerWith('campaign', 1000, ['rarity' => 'legendary', 'level' => 12]);
        $defender = $this->playerWith('defense', 1000);
        $this->actingAs($attacker)->postJson("/api/arena/challenge/{$defender->id}")->assertOk();

        $this->actingAs($attacker)->getJson('/api/arena/ranking')
            ->assertOk()
            ->assertJsonStructure(['top' => [['rank', 'name', 'rating', 'league']], 'me' => ['rank', 'rating']])
            ->assertJsonPath('top.0.rank', 1);

        $this->actingAs($attacker)->getJson('/api/arena/history')
            ->assertOk()
            ->assertJsonPath('history.0.role', 'attack')
            ->assertJsonPath('history.0.opponent', $defender->name)
            ->assertJsonPath('history.0.won', true);

        $this->actingAs($defender)->getJson('/api/arena/history')
            ->assertJsonPath('history.0.role', 'defense')
            ->assertJsonPath('history.0.won', false);
    }
}
