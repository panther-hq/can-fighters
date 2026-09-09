<?php

namespace Tests\Unit\Battle;

use App\Domain\Battle\BattleEngine;
use App\Domain\Battle\SkillResolver;
use PHPUnit\Framework\TestCase;

class BattleEngineTest extends TestCase
{
    use BattleTestHelpers;

    private function engine(): BattleEngine
    {
        return new BattleEngine(new SkillResolver);
    }

    public function test_same_seed_produces_an_identical_battle(): void
    {
        $combatants = [
            $this->combatant(['id' => 1, 'team' => 'A', 'class' => 'fighter', 'skills' => [$this->skill('direct_damage', ['power' => 20, 'cooldown' => 2])]]),
            $this->combatant(['id' => 2, 'team' => 'A', 'class' => 'assassin', 'speed' => 20]),
            $this->combatant(['id' => 3, 'team' => 'B', 'class' => 'tank', 'hp' => 200, 'skills' => [$this->skill('taunt', ['duration' => 2, 'cooldown' => 3])]]),
            $this->combatant(['id' => 4, 'team' => 'B', 'class' => 'mage', 'skills' => [$this->skill('area_damage', ['power' => 15, 'targets' => 2, 'cooldown' => 2])]]),
        ];

        $a = $this->engine()->run($combatants, 987654);
        $b = $this->engine()->run($combatants, 987654);

        $this->assertSame(json_encode($a), json_encode($b));
        $this->assertSame($a->winner, $b->winner);
    }

    public function test_a_different_seed_changes_the_battle(): void
    {
        $combatants = [
            $this->combatant(['id' => 1, 'team' => 'A', 'crit' => 30]),
            $this->combatant(['id' => 2, 'team' => 'B', 'crit' => 30]),
        ];

        $a = $this->engine()->run($combatants, 1);
        $b = $this->engine()->run($combatants, 2);

        $this->assertNotSame(json_encode($a->events), json_encode($b->events));
    }

    public function test_the_stronger_team_wins_and_the_loser_is_wiped(): void
    {
        $combatants = [
            $this->combatant(['id' => 1, 'team' => 'A', 'hp' => 300, 'attack' => 60, 'defense' => 30]),
            $this->combatant(['id' => 2, 'team' => 'B', 'hp' => 80, 'attack' => 6, 'defense' => 2]),
        ];

        $result = $this->engine()->run($combatants, 42);

        $this->assertSame('A', $result->winner);
        $this->assertSame([1], $result->survivorsA);
        $this->assertSame([], $result->survivorsB);
        $this->assertNotEmpty(array_filter($result->events, fn ($e) => $e->type === 'death' && $e->target === 2));
        $this->assertSame('battle_end', $result->events[array_key_last($result->events)]->type);
    }

    public function test_events_are_sequential_and_time_ordered(): void
    {
        $combatants = [
            $this->combatant(['id' => 1, 'team' => 'A']),
            $this->combatant(['id' => 2, 'team' => 'B']),
        ];

        $result = $this->engine()->run($combatants, 7);

        $lastSeq = 0;
        $lastTime = -1;
        foreach ($result->events as $event) {
            $this->assertSame($lastSeq + 1, $event->sequence);
            $this->assertGreaterThanOrEqual($lastTime, $event->time);
            $lastSeq = $event->sequence;
            $lastTime = $event->time;
        }
    }

    public function test_a_faster_fighter_acts_first(): void
    {
        $combatants = [
            $this->combatant(['id' => 1, 'team' => 'A', 'speed' => 5]),
            $this->combatant(['id' => 2, 'team' => 'B', 'speed' => 40]),
        ];

        $result = $this->engine()->run($combatants, 3);
        $firstAction = collect($result->events)->firstWhere('type', 'damage');

        $this->assertSame(2, $firstAction->source);
    }

    public function test_two_mirror_teams_can_draw_out_but_still_terminate(): void
    {
        $combatants = [
            $this->combatant(['id' => 1, 'team' => 'A', 'hp' => 999, 'attack' => 1, 'defense' => 99]),
            $this->combatant(['id' => 2, 'team' => 'B', 'hp' => 999, 'attack' => 1, 'defense' => 99]),
        ];

        $result = $this->engine()->run($combatants, 5);

        $this->assertContains($result->winner, ['A', 'B', 'draw']);
        $this->assertLessThanOrEqual(60_000, $result->duration);
    }
}
