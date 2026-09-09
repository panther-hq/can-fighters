<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_player_can_register_and_gets_a_profile(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Stefan',
            'email' => 'stefan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Stefan')
            ->assertJsonPath('data.email', 'stefan@example.com')
            ->assertJsonPath('data.profile.level', 1)
            ->assertJsonPath('data.profile.coins', 0)
            ->assertJsonPath('data.profile.rating', 1000);

        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'stefan@example.com');
        $this->assertNotNull($user);
        $this->assertNotNull($user->playerProfile);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Stefan',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Stefan',
            'email' => 'stefan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'nope',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_it_does_not_create_a_user_when_validation_fails(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'x',
            'email' => 'not-an-email',
            'password' => 'short',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('player_profiles', 0);
    }
}
