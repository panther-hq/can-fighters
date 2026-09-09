<?php

namespace Tests\Feature\PvE;

use App\Models\PlayerRegionRun;
use App\Models\Team;
use App\Models\User;
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
     * A tiny hand-built map: row 0 = loot + battle + merchant, row 1 = boss.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function craftRun(User $user, int $battleBudget = 40): PlayerRegionRun
    {
        $enemies = fn () => [
            ['name' => 'Chrupka', 'class' => 'fighter'],
            ['name' => 'Okruszek', 'class' => 'ranged'],
            ['name' => 'Skórka', 'class' => 'tank'],
        ];

        return PlayerRegionRun::create([
            'user_id' => $user->id,
            'region_slug' => 'kitchen',
            'seed' => 555,
            'current_row' => -1,
            'cleared_node_ids' => [],
            'status' => 'active',
            'map' => [
                'regionSlug' => 'kitchen',
                'seed' => 555,
                'rows' => [
                    ['row' => 0, 'nodes' => [
                        ['id' => '0-0', 'row' => 0, 'col' => 0, 'type' => 'loot', 'edges' => ['boss']],
                        ['id' => '0-1', 'row' => 0, 'col' => 1, 'type' => 'battle', 'edges' => ['boss'], 'budget' => $battleBudget, 'enemies' => $enemies()],
                        ['id' => '0-2', 'row' => 0, 'col' => 2, 'type' => 'merchant', 'edges' => ['boss']],
                    ]],
                    ['row' => 1, 'nodes' => [
                        ['id' => 'boss', 'row' => 1, 'col' => 0, 'type' => 'boss', 'edges' => [], 'budget' => $battleBudget, 'enemies' => $enemies()],
                    ]],
                ],
            ],
        ]);
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

    public function test_starting_a_run_builds_a_map(): void
    {
        $user = $this->player();

        $this->actingAs($user)->postJson('/api/pve/regions/kitchen/run')
            ->assertOk()
            ->assertJsonPath('run.regionSlug', 'kitchen')
            ->assertJsonPath('run.status', 'active')
            ->assertJsonCount(6, 'run.map.rows');

        $this->assertNotEmpty($this->actingAs($user)->getJson('/api/pve/run')->json('run.reachableNodeIds'));
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

    public function test_visiting_a_loot_node_grants_a_reward_and_advances(): void
    {
        $user = $this->player();
        $run = $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/visit/0-0')
            ->assertOk()
            ->assertJsonPath('type', 'loot')
            ->assertJsonPath('run.clearedNodeIds', ['0-0'])
            ->assertJsonPath('run.reachableNodeIds', ['boss']);
    }

    public function test_winning_a_battle_node_advances_the_run(): void
    {
        $user = $this->player(['rarity' => 'legendary', 'level' => 14]);
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/visit/0-1')
            ->assertOk()
            ->assertJsonPath('won', true)
            ->assertJsonStructure(['battleId', 'result' => ['winner'], 'rewards']);

        $this->assertGreaterThan(1000, $user->playerProfile->fresh()->coins);
    }

    public function test_losing_a_battle_ends_the_run(): void
    {
        $user = $this->player(); // plain common level-1 fighters
        $run = $this->craftRun($user, battleBudget: 500); // brutally over-budget enemies

        $this->actingAs($user)->postJson('/api/pve/run/visit/0-1')
            ->assertOk()
            ->assertJsonPath('won', false)
            ->assertJsonPath('runEnded', true);

        $this->assertSame('abandoned', $run->fresh()->status);
    }

    public function test_the_merchant_flow(): void
    {
        $user = $this->player();
        $run = $this->craftRun($user);

        $offers = $this->actingAs($user)->postJson('/api/pve/run/visit/0-2')
            ->assertOk()
            ->assertJsonPath('type', 'merchant')
            ->json('offers');

        $this->assertNotEmpty($offers);
        $before = $user->playerProfile->fresh()->coins;

        $this->actingAs($user)->postJson('/api/pve/run/buy', ['offerId' => $offers[0]['id']])
            ->assertOk()
            ->assertJsonPath('run.activeMerchant.offers.0.bought', true);

        $this->assertLessThan($before, $user->playerProfile->fresh()->coins);

        $this->actingAs($user)->postJson('/api/pve/run/advance')
            ->assertOk()
            ->assertJsonPath('run.clearedNodeIds', ['0-2'])
            ->assertJsonPath('run.activeMerchant', null);
    }

    public function test_clearing_the_boss_completes_the_region_and_unlocks_the_next(): void
    {
        $user = $this->player(['rarity' => 'legendary', 'level' => 16]);
        $this->craftRun($user);

        // clear a row-0 node to reach the boss
        $this->actingAs($user)->postJson('/api/pve/run/visit/0-0')->assertOk();
        $this->actingAs($user)->postJson('/api/pve/run/visit/boss')
            ->assertOk()
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

    public function test_you_cannot_visit_an_unreachable_node(): void
    {
        $user = $this->player();
        $this->craftRun($user);

        $this->actingAs($user)->postJson('/api/pve/run/visit/boss')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tam nie możesz teraz przejść.');
    }
}
