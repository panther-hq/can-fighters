<?php

namespace Tests\Unit;

use App\Domain\Live\LiveRoundResolver;
use Tests\TestCase;

class LiveRoundResolverTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function unit(int $id, string $team, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'name' => "U{$id}",
            'team' => $team,
            'position' => 'front',
            'class' => 'fighter',
            'maxHp' => 200,
            'hp' => 200,
            'energy' => 6,
            'atk' => 30,
            'def' => 10,
            'mag' => 10,
            'spd' => 12,
            'crit' => 0,
            'shield' => 0,
            'stunUntilRound' => 0,
            'cooldowns' => (object) [],
            'effects' => [],
            'skills' => [
                ['slot' => 0, 'family' => 'direct_damage', 'params' => ['power' => 40, 'cooldown' => 2]],
                ['slot' => 1, 'family' => 'heal', 'params' => ['power' => 50, 'cooldown' => 2]],
            ],
        ], $overrides);
    }

    private function resolver(): LiveRoundResolver
    {
        return new LiveRoundResolver;
    }

    public function test_two_basic_attacks_deal_damage_and_are_deterministic(): void
    {
        $state = ['units' => [$this->unit(1, 'A'), $this->unit(2, 'B')], 'round' => 1];
        $actions = [
            ['actorId' => 1, 'type' => 'attack', 'targetId' => 2],
            ['actorId' => 2, 'type' => 'attack', 'targetId' => 1],
        ];

        $a = $this->resolver()->resolve($state, $actions, 1, 4242);
        $b = $this->resolver()->resolve($state, $actions, 1, 4242);

        $this->assertSame(json_encode($a), json_encode($b));
        $units = collect($a['state']['units'])->keyBy('id');
        $this->assertLessThan(200, $units[1]['hp']);
        $this->assertLessThan(200, $units[2]['hp']);
        $this->assertSame(2, $a['state']['round']);
    }

    public function test_a_damage_skill_spends_energy_and_sets_a_cooldown(): void
    {
        $state = ['units' => [$this->unit(1, 'A'), $this->unit(2, 'B')], 'round' => 1];
        $actions = [['actorId' => 1, 'type' => 'skill', 'slot' => 0, 'targetId' => 2]];

        $out = $this->resolver()->resolve($state, $actions, 1, 1);

        $attacker = collect($out['state']['units'])->firstWhere('id', 1);
        $target = collect($out['state']['units'])->firstWhere('id', 2);
        $this->assertLessThan(200, $target['hp']);
        $this->assertGreaterThanOrEqual(3, (int) ((array) $attacker['cooldowns'])['0']); // round 1 + cd 2
        $this->assertLessThan(6 + (int) config('live.energy_per_round'), $attacker['energy']);
    }

    public function test_heal_restores_the_most_hurt_ally(): void
    {
        $healer = $this->unit(1, 'A');
        $hurt = $this->unit(2, 'A', ['hp' => 40]);
        $state = ['units' => [$healer, $hurt], 'round' => 1];

        $out = $this->resolver()->resolve($state, [
            ['actorId' => 1, 'type' => 'skill', 'slot' => 1, 'targetId' => 2],
        ], 1, 9);

        $this->assertGreaterThan(40, collect($out['state']['units'])->firstWhere('id', 2)['hp']);
    }

    public function test_a_finishing_blow_reports_the_winner(): void
    {
        $state = [
            'units' => [$this->unit(1, 'A'), $this->unit(2, 'B', ['hp' => 5, 'def' => 0])],
            'round' => 1,
        ];

        $out = $this->resolver()->resolve($state, [
            ['actorId' => 1, 'type' => 'skill', 'slot' => 0, 'targetId' => 2],
        ], 1, 3);

        $this->assertSame('A', $out['winner']);
        $this->assertSame(0, collect($out['state']['units'])->firstWhere('id', 2)['hp']);
    }

    public function test_energy_regenerates_each_round_up_to_the_cap(): void
    {
        $state = ['units' => [$this->unit(1, 'A', ['energy' => 0]), $this->unit(2, 'B')], 'round' => 1];

        $out = $this->resolver()->resolve($state, [
            ['actorId' => 1, 'type' => 'attack', 'targetId' => 2],
        ], 1, 1);

        $this->assertSame((int) config('live.energy_per_round'), collect($out['state']['units'])->firstWhere('id', 1)['energy']);
    }
}
