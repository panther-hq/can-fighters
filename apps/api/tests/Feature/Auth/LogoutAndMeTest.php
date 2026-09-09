<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutAndMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_player_with_profile(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => 250]);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.profile.coins', 250);
    }

    public function test_a_player_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/logout')->assertNoContent();

        // Logout's job is to end the cookie session (the `web` guard).
        $this->assertGuest('web');
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
