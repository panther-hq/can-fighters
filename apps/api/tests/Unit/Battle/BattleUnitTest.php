<?php

namespace Tests\Unit\Battle;

use App\Domain\Battle\BattleUnit;
use PHPUnit\Framework\TestCase;

class BattleUnitTest extends TestCase
{
    use BattleTestHelpers;

    public function test_shield_absorbs_before_hp(): void
    {
        $unit = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'hp' => 100]));
        $unit->addShield(30, 5000);

        $lost = $unit->takeDamage(50);

        $this->assertSame(20, $lost);
        $this->assertSame(80, $unit->hp);
        $this->assertSame(0, $unit->shield);
    }

    public function test_heal_does_not_exceed_max_hp(): void
    {
        $unit = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'hp' => 100]));
        $unit->takeDamage(40);

        $this->assertSame(40, $unit->heal(999));
        $this->assertSame(100, $unit->hp);
    }

    public function test_stat_mods_stack_multiplicatively_and_expire(): void
    {
        $unit = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'attack' => 100]));
        $unit->addStatMod('attack', 20, 5000);
        $unit->addStatMod('attack', -50, 5000);

        $this->assertSame(60, $unit->stat('attack', 1000)); // 100 * 1.2 * 0.5
        $this->assertSame(100, $unit->stat('attack', 6000)); // both expired
    }

    public function test_dot_ticks_once_per_call_until_it_expires(): void
    {
        $unit = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A']));
        $unit->addDot(7, expiresAt: 3000, source: 9, kind: 'poison', now: 0);

        $this->assertCount(1, $unit->tick(1000));
        $this->assertCount(1, $unit->tick(2000));
        $this->assertCount(0, $unit->tick(3000)); // expired
    }

    public function test_magic_classes_hit_with_magic(): void
    {
        $mage = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'class' => 'mage', 'attack' => 5, 'magic' => 40]));
        $warrior = new BattleUnit($this->combatant(['id' => 2, 'team' => 'A', 'class' => 'fighter', 'attack' => 40, 'magic' => 5]));

        $this->assertSame(40, $mage->basicPower(0));
        $this->assertSame(40, $warrior->basicPower(0));
    }

    public function test_higher_speed_means_a_shorter_interval(): void
    {
        $slow = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'speed' => 5]));
        $fast = new BattleUnit($this->combatant(['id' => 2, 'team' => 'A', 'speed' => 40]));

        $this->assertLessThan($slow->actionInterval(0), $fast->actionInterval(0));
    }
}
