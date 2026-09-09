<?php

namespace Tests\Feature\Fighters;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class FighterDetailsTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    public function test_details_include_stats_and_skills(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user);

        $this->actingAs($user)
            ->getJson("/api/fighters/{$fighter->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'primaryClass',
                    'stats' => ['hp', 'attack', 'defense', 'magic', 'speed', 'crit', 'powerScore'],
                    'skills' => [['slot', 'skillFamily', 'parameters']],
                ],
            ]);
    }

    public function test_list_includes_stats(): void
    {
        $user = User::factory()->create();
        $this->makeFighter($user);

        $this->actingAs($user)
            ->getJson('/api/fighters')
            ->assertOk()
            ->assertJsonPath('data.0.stats.hp', fn ($hp) => $hp > 0);
    }

    public function test_cannot_view_someone_elses_fighter(): void
    {
        $fighter = $this->makeFighter(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/fighters/{$fighter->id}")
            ->assertNotFound();
    }
}
