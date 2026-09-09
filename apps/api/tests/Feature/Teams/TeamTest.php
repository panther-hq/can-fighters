<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    public function test_team_requires_authentication(): void
    {
        $this->getJson('/api/teams')->assertUnauthorized();
        $this->putJson('/api/teams', ['members' => []])->assertUnauthorized();
    }

    public function test_team_is_null_until_set_up(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/teams')
            ->assertOk()
            ->assertJsonPath('team', null);
    }

    public function test_a_player_can_set_and_read_their_team(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFighter($user);
        $b = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [
                ['fighterId' => $b->id, 'position' => 'back'],
                ['fighterId' => $a->id, 'position' => 'front'],
            ],
        ])->assertOk()->assertJsonPath('team.members.0.position', 'front');

        $this->actingAs($user)->getJson('/api/teams')
            ->assertOk()
            ->assertJsonCount(2, 'team.members')
            ->assertJsonPath('team.members.0.fighter.id', $a->id)
            ->assertJsonPath('team.members.1.fighter.id', $b->id);
    }

    public function test_updating_replaces_the_previous_roster(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFighter($user);
        $b = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [['fighterId' => $a->id, 'position' => 'front']],
        ])->assertOk();

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [['fighterId' => $b->id, 'position' => 'middle']],
        ])->assertOk()->assertJsonCount(1, 'team.members')
            ->assertJsonPath('team.members.0.fighter.id', $b->id);
    }

    public function test_rejects_more_than_three_members(): void
    {
        $user = User::factory()->create();
        $fighters = collect(range(1, 4))->map(fn () => $this->makeFighter($user));

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => $fighters->map(fn ($f, $i) => [
                'fighterId' => $f->id,
                'position' => ['front', 'middle', 'back', 'front'][$i],
            ])->all(),
        ])->assertStatus(422);
    }

    public function test_rejects_a_duplicated_position(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFighter($user);
        $b = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [
                ['fighterId' => $a->id, 'position' => 'front'],
                ['fighterId' => $b->id, 'position' => 'front'],
            ],
        ])->assertStatus(422)->assertJsonPath('message', 'Każda pozycja może być zajęta tylko raz.');
    }

    public function test_rejects_the_same_fighter_twice(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [
                ['fighterId' => $a->id, 'position' => 'front'],
                ['fighterId' => $a->id, 'position' => 'back'],
            ],
        ])->assertStatus(422);
    }

    public function test_rejects_an_invalid_position(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [['fighterId' => $a->id, 'position' => 'sky']],
        ])->assertStatus(422);
    }

    public function test_rejects_a_fighter_you_do_not_own(): void
    {
        $user = User::factory()->create();
        $foreign = $this->makeFighter(User::factory()->create());

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [['fighterId' => $foreign->id, 'position' => 'front']],
        ])->assertStatus(422)->assertJsonPath('message', 'Wybrany wojownik do Ciebie nie należy.');
    }

    public function test_bootstrap_includes_the_team(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create([]);
        $a = $this->makeFighter($user);

        $this->actingAs($user)->putJson('/api/teams', [
            'members' => [['fighterId' => $a->id, 'position' => 'front']],
        ])->assertOk();

        $this->actingAs($user)->getJson('/api/game/bootstrap')
            ->assertOk()
            ->assertJsonPath('team.members.0.fighter.id', $a->id);
    }
}
