<?php

namespace Tests\Feature\PvE;

use App\Models\PlayerRegionRun;
use App\Models\Team;
use App\Models\User;
use App\Support\SeededRng;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class RegionRunTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $fighterAttributes
     */
    private function player(array $fighterAttributes = []): User
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => 1000, 'xp' => 0]);
        $team = $user->teams()->create(['type' => 'campaign']);
        foreach (Team::POSITIONS as $i => $position) {
            $team->members()->create(['fighter_id' => $this->makeFighter($user, $fighterAttributes)->id, 'position' => $position]);
        }

        return $user;
    }

    /**
     * An 8x8 all-grass map (one rock at 5,5) fully revealed. Hero at (0,0);
     * treasure at (2,0), a roaming enemy at (0,2), a "?" at (2,2), boss at (4,0).
     */
    private function craftRun(User $user, int $enemyBudget = 40, int $bossBudget = 40, int $seed = 555): PlayerRegionRun
    {
        $enemies = fn () => [
            ['name' => 'Chrupka', 'class' => 'fighter'],
            ['name' => 'Okruszek', 'class' => 'ranged'],
            ['name' => 'Skórka', 'class' => 'tank'],
        ];

        $terrain = array_fill(0, 64, 'grass');
        $terrain[5 * 8 + 5] = 'rock';

        $revealed = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $revealed[] = "{$x},{$y}";
            }
        }

        return PlayerRegionRun::create([
            'user_id' => $user->id,
            'region_slug' => 'kitchen',
            'seed' => $seed,
            'map' => [
                'regionSlug' => 'kitchen',
                'seed' => $seed,
                'width' => 8,
                'height' => 8,
                'terrain' => $terrain,
                'start' => ['x' => 0, 'y' => 0],
                'objects' => [
                    ['id' => 'boss', 'x' => 4, 'y' => 0, 'kind' => 'boss', 'tier' => 6, 'budget' => $bossBudget, 'enemies' => $enemies()],
                    ['id' => 't0', 'x' => 2, 'y' => 0, 'kind' => 'treasure', 'reward' => ['type' => 'coins', 'amount' => 100]],
                    ['id' => 'e0', 'x' => 0, 'y' => 2, 'kind' => 'enemy', 'elite' => false, 'tier' => 1, 'budget' => $enemyBudget, 'enemies' => $enemies()],
                    ['id' => 'ev0', 'x' => 2, 'y' => 2, 'kind' => 'event'],
                ],
            ],
            'hero_x' => 0,
            'hero_y' => 0,
            'movement_left' => 6,
            'movement_max' => 6,
            'day' => 1,
            'revealed' => $revealed,
            'resolved_object_ids' => [],
            'status' => 'active',
        ]);
    }

    /** Brute-forces a run seed whose "?" at $objectId on $day rolls the wanted event. */
    private function seedForEvent(string $want, string $objectId, int $day): int
    {
        $weights = ['skarb' => 4, 'trening' => 3, 'handlarz' => 2, 'pulapka' => 2];
        for ($seed = 1; $seed < 100_000; $seed++) {
            $rng = new SeededRng($seed + crc32('event'.$objectId) + $day);
            $roll = $rng->int(array_sum($weights));
            $acc = 0;
            $got = 'skarb';
            foreach ($weights as $key => $weight) {
                $acc += $weight;
                if ($roll < $acc) {
                    $got = $key;
                    break;
                }
            }
            if ($got === $want) {
                return $seed;
            }
        }

        $this->fail("no seed rolls {$want}");
    }

    public function test_regions_list_shows_unlock_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/pve/regions')
            ->assertOk()
            ->assertJsonCount(4, 'regions')
            ->assertJsonPath('regions.0.slug', 'kitchen')
            ->assertJsonPath('regions.0.unlocked', true)
            ->assertJsonPath('regions.1.unlocked', false)
            ->assertJsonPath('run', null);
    }

    public function test_starting_a_run_builds_a_tile_map_with_the_hero_at_the_start(): void
    {
        $user = $this->player();

        $run = $this->actingAs($user)->postJson('/api/pve/regions/kitchen/run')
            ->assertOk()
            ->assertJsonPath('run.regionSlug', 'kitchen')
            ->assertJsonPath('run.status', 'active')
            ->assertJsonPath('run.day', 1)
            ->json('run');

        $w = $run['size']['width'];
        $this->assertSame($w * $run['size']['height'], count($run['terrain']));
        $this->assertSame($run['movementMax'], $run['movementLeft']);
        $this->assertNotContains($run['terrain'][$run['hero']['y'] * $w + $run['hero']['x']], ['rock', 'water']);
        $this->assertNotContains('7,7', $run['revealed']); // fog of war hides the far corner
        $this->assertContains("{$run['hero']['x']},{$run['hero']['y']}", $run['revealed']);
    }

    public function test_a_locked_region_cannot_be_started(): void
    {
        $this->actingAs($this->player())
            ->postJson('/api/pve/regions/garage/run')
            ->assertStatus(403);
    }

    public function test_a_run_needs_a_team(): void
    {
        $user = User::factory()->create();
        $user->playerProfile()->create([]);

        $this->actingAs($user)->postJson('/api/pve/regions/kitchen/run')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Najpierw ustaw drużynę.');
    }

    public function test_starting_a_new_run_abandons_the_old_one(): void
    {
        $user = $this->player();
        $first = $this->actingAs($user)->postJson('/api/pve/regions/kitchen/run')->json('run.runId');
        $this->actingAs($user)->postJson('/api/pve/regions/kitchen/run')->assertOk();

        $this->assertSame('abandoned', PlayerRegionRun::find($first)->status);
    }

    public function test_walking_onto_a_treasure_grants_loot_and_clears_it(): void
    {
        $user = $this->player();
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 2, 'y' => 0])
            ->assertOk()
            ->assertJsonPath('type', 'loot')
            ->assertJsonPath('run.hero.x', 2)
            ->assertJsonPath('run.movementLeft', 4)
            ->assertJsonMissing(['id' => 't0']);

        $this->assertSame(1100, $user->playerProfile->fresh()->coins);
    }

    public function test_walking_partway_stops_when_the_day_runs_out(): void
    {
        $user = $this->player();
        $this->craftRun($user);

        $result = $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 7, 'y' => 7])
            ->assertOk()
            ->assertJsonPath('type', 'move')
            ->assertJsonPath('run.movementLeft', 0)
            ->json();

        $this->assertNotSame([7, 7], [$result['run']['hero']['x'], $result['run']['hero']['y']]);
        $this->assertCount(7, $result['path']); // start tile + 6 steps
    }

    public function test_ending_the_day_refills_movement_and_advances_the_day(): void
    {
        $user = $this->player();
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 6, 'y' => 0]); // drains all 6

        $this->actingAs($user)->postJson('/api/pve/run/end-day')
            ->assertOk()
            ->assertJsonPath('type', 'day')
            ->assertJsonPath('run.day', 2)
            ->assertJsonPath('run.movementLeft', 6);
    }

    public function test_you_cannot_walk_onto_impassable_terrain(): void
    {
        $user = $this->player();
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 5, 'y' => 5])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tam nie da się wejść.');
    }

    public function test_winning_a_battle_clears_the_enemy(): void
    {
        $user = $this->player(['rarity' => 'legendary', 'level' => 14]);
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 0, 'y' => 2])
            ->assertOk()
            ->assertJsonPath('type', 'battle')
            ->assertJsonPath('won', true)
            ->assertJsonStructure(['battleId', 'result' => ['winner'], 'rewards', 'path'])
            ->assertJsonMissing(['id' => 'e0']);

        $this->assertGreaterThan(1000, $user->playerProfile->fresh()->coins);
    }

    public function test_losing_a_battle_ends_the_run(): void
    {
        $user = $this->player();
        $run = $this->craftRun($user, enemyBudget: 500);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 0, 'y' => 2])
            ->assertOk()
            ->assertJsonPath('won', false)
            ->assertJsonPath('runEnded', true);

        $this->assertSame('abandoned', $run->fresh()->status);
    }

    public function test_the_merchant_flow(): void
    {
        $seed = $this->seedForEvent('handlarz', 'ev0', 1);
        $user = $this->player();
        $this->craftRun($user, seed: $seed);

        $offers = $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 2, 'y' => 2])
            ->assertOk()
            ->assertJsonPath('type', 'merchant')
            ->json('offers');

        $this->assertNotEmpty($offers);
        $before = $user->playerProfile->fresh()->coins;

        $this->actingAs($user)->postJson('/api/pve/run/buy', ['offerId' => $offers[0]['id']])
            ->assertOk()
            ->assertJsonPath('run.activeMerchant.offers.0.bought', true);

        $this->assertLessThan($before, $user->playerProfile->fresh()->coins);

        $this->actingAs($user)->postJson('/api/pve/run/leave-merchant')
            ->assertOk()
            ->assertJsonPath('run.activeMerchant', null)
            ->assertJsonMissing(['id' => 'ev0']);
    }

    public function test_you_cannot_move_while_the_merchant_is_open(): void
    {
        $seed = $this->seedForEvent('handlarz', 'ev0', 1);
        $user = $this->player();
        $this->craftRun($user, seed: $seed);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 2, 'y' => 2])->assertOk();

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 1, 'y' => 0])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Najpierw opuść kupca.');
    }

    public function test_beating_the_boss_completes_the_region_and_unlocks_the_next(): void
    {
        $user = $this->player(['rarity' => 'legendary', 'level' => 16]);
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 2, 'y' => 0])->assertOk(); // clear treasure, unblock the road
        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 4, 'y' => 0])
            ->assertOk()
            ->assertJsonPath('type', 'battle')
            ->assertJsonPath('won', true)
            ->assertJsonPath('run.status', 'cleared');

        $this->assertDatabaseHas('player_region_clears', ['user_id' => $user->id, 'region_slug' => 'kitchen', 'times_cleared' => 1]);

        $this->actingAs($user)->getJson('/api/pve/regions')
            ->assertJsonPath('regions.1.unlocked', true);
    }

    public function test_abandon_ends_the_run(): void
    {
        $user = $this->player();
        $run = $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/abandon')->assertOk()->assertJsonPath('run', null);
        $this->assertSame('abandoned', $run->fresh()->status);
    }

    public function test_you_cannot_move_after_the_run_ended(): void
    {
        $user = $this->player();
        $run = $this->craftRun($user);
        $run->update(['status' => 'cleared']);

        $this->actingAs($user)->postJson('/api/pve/run/move', ['x' => 1, 'y' => 0])
            ->assertStatus(404); // no active run
    }
}
