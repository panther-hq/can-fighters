<?php

namespace Tests\Unit\Battle;

use App\Domain\Battle\BattleEngine;
use App\Domain\Battle\SkillResolver;
use App\Domain\Battle\ValueObjects\BattleResult;
use PHPUnit\Framework\TestCase;

class SkillEffectsTest extends TestCase
{
    use BattleTestHelpers;

    private function simulate(array $combatants, int $seed = 11): BattleResult
    {
        return (new BattleEngine(new SkillResolver))->run($combatants, $seed);
    }

    private function events(BattleResult $r, string $type): array
    {
        return array_values(array_filter($r->events, fn ($e) => $e->type === $type));
    }

    public function test_heal_restores_a_hurt_ally(): void
    {
        $result = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'class' => 'support', 'hp' => 200, 'skills' => [$this->skill('heal', ['power' => 40, 'cooldown' => 1])]]),
            $this->combatant(['id' => 2, 'team' => 'A', 'class' => 'fighter', 'hp' => 90]),
            $this->combatant(['id' => 3, 'team' => 'B', 'class' => 'fighter', 'attack' => 25]),
        ]);

        $heals = $this->events($result, 'heal');
        $this->assertNotEmpty($heals);
        $this->assertTrue(
            collect($heals)->contains(fn ($e) => $e->jsonSerialize()['amount'] > 0),
            'expected at least one heal to restore HP',
        );
    }

    public function test_stun_with_full_chance_lands_and_makes_the_target_skip_a_turn(): void
    {
        $result = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'speed' => 30, 'skills' => [$this->skill('stun', ['chance' => 100, 'duration' => 3, 'cooldown' => 5])]]),
            $this->combatant(['id' => 2, 'team' => 'B', 'hp' => 400, 'speed' => 5]),
        ]);

        $effects = $this->events($result, 'effect');
        $stun = collect($effects)->first(fn ($e) => ($e->jsonSerialize()['effect'] ?? null) === 'stun');
        $this->assertNotNull($stun);
        $this->assertTrue($stun->jsonSerialize()['applied']);
        $this->assertNotEmpty($this->events($result, 'stunned'));
    }

    public function test_poison_deals_damage_over_several_turns(): void
    {
        $result = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'class' => 'debuffer', 'skills' => [$this->skill('poison', ['power' => 12, 'duration' => 3, 'cooldown' => 5])]]),
            $this->combatant(['id' => 2, 'team' => 'B', 'hp' => 500, 'defense' => 40]),
        ]);

        $poisonTicks = array_filter(
            $this->events($result, 'damage'),
            fn ($e) => ($e->jsonSerialize()['cause'] ?? null) === 'poison',
        );

        $this->assertGreaterThanOrEqual(2, count($poisonTicks));
    }

    public function test_shield_absorbs_incoming_damage(): void
    {
        $noShield = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'hp' => 150, 'skills' => []]),
            $this->combatant(['id' => 2, 'team' => 'B', 'attack' => 20]),
        ], 4);

        $shielded = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'hp' => 150, 'class' => 'crafter', 'skills' => [$this->skill('shield', ['power' => 60, 'duration' => 3, 'cooldown' => 1])]]),
            $this->combatant(['id' => 2, 'team' => 'B', 'attack' => 20]),
        ], 4);

        $this->assertNotEmpty(collect($shielded->events)->filter(
            fn ($e) => $e->type === 'effect' && ($e->jsonSerialize()['effect'] ?? null) === 'shield',
        ));
        // The shielded fighter should live longer (more of its events before it dies).
        $this->assertGreaterThanOrEqual($noShield->duration, $shielded->duration);
    }

    public function test_buff_and_debuff_emit_effect_events(): void
    {
        $result = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'skills' => [$this->skill('buff_attack', ['amount' => 40, 'duration' => 3, 'cooldown' => 5])]]),
            $this->combatant(['id' => 2, 'team' => 'B', 'class' => 'debuffer', 'hp' => 300, 'skills' => [$this->skill('debuff_defense', ['amount' => 40, 'duration' => 3, 'cooldown' => 5])]]),
        ]);

        $labels = collect($this->events($result, 'effect'))
            ->map(fn ($e) => $e->jsonSerialize()['effect'] ?? null)
            ->all();

        $this->assertContains('buff_attack', $labels);
        $this->assertContains('debuff_defense', $labels);
    }

    public function test_lifesteal_heals_the_attacker(): void
    {
        $result = $this->simulate([
            $this->combatant(['id' => 1, 'team' => 'A', 'hp' => 120, 'skills' => [$this->skill('lifesteal', ['power' => 30, 'ratio' => 60, 'cooldown' => 1])]]),
            $this->combatant(['id' => 2, 'team' => 'B', 'hp' => 400, 'attack' => 22]),
        ]);

        $selfHeals = array_filter(
            $this->events($result, 'heal'),
            fn ($e) => $e->source === 1 && $e->target === 1,
        );

        $this->assertNotEmpty($selfHeals);
    }
}
