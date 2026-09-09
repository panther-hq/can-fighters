<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $user->playerProfile()->create([]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.profile.level', 1);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_fails_for_an_unknown_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();

        $this->assertGuest();
    }
}
