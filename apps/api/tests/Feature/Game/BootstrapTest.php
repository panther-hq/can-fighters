<?php

namespace Tests\Feature\Game;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_requires_authentication(): void
    {
        $this->getJson('/api/game/bootstrap')->assertUnauthorized();
    }

    public function test_bootstrap_returns_the_expected_shape(): void
    {
        $user = User::factory()->create(['name' => 'Stefan']);
        $user->playerProfile()->create(['level' => 3, 'xp' => 120, 'coins' => 500, 'rating' => 1042]);

        $response = $this->actingAs($user)->getJson('/api/game/bootstrap');

        $response->assertOk()
            ->assertJsonStructure([
                'player' => ['id', 'displayName', 'profile' => ['level', 'xp', 'coins', 'rating']],
                'currencies' => ['coins'],
                'team',
                'cans',
                'notifications',
                'serverTime',
            ])
            ->assertJsonPath('player.id', $user->id)
            ->assertJsonPath('player.displayName', 'Stefan')
            ->assertJsonPath('player.profile.level', 3)
            ->assertJsonPath('currencies.coins', 500)
            ->assertJsonPath('team', null)
            ->assertExactJson([
                'player' => [
                    'id' => $user->id,
                    'displayName' => 'Stefan',
                    'profile' => ['level' => 3, 'xp' => 120, 'coins' => 500, 'rating' => 1042],
                ],
                'currencies' => ['coins' => 500],
                'team' => null,
                'cans' => [],
                'pve' => ['stagesCleared' => 0, 'stagesTotal' => $response->json('pve.stagesTotal')],
                'notifications' => [],
                'serverTime' => $response->json('serverTime'),
            ]);
    }
}
