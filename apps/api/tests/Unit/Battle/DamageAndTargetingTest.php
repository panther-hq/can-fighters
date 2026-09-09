<?php

namespace Tests\Unit\Battle;

use App\Domain\Battle\BattleUnit;
use App\Domain\Battle\DamageCalculator;
use App\Domain\Battle\TargetSelector;
use App\Support\SeededRng;
use PHPUnit\Framework\TestCase;

class DamageAndTargetingTest extends TestCase
{
    use BattleTestHelpers;

    public function test_more_defense_means_less_damage(): void
    {
        $attacker = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'crit' => 0]));
        $squishy = new BattleUnit($this->combatant(['id' => 2, 'team' => 'B', 'defense' => 0]));
        $armored = new BattleUnit($this->combatant(['id' => 3, 'team' => 'B', 'defense' => 100]));

        $calc = new DamageCalculator(new SeededRng(1));
        $soft = $calc->resolve(100, $attacker, $squishy, 0)['damage'];
        $hard = $calc->resolve(100, $attacker, $armored, 0)['damage'];

        $this->assertGreaterThan($hard, $soft);
        $this->assertGreaterThanOrEqual(1, $hard);
    }

    public function test_crit_multiplies_damage(): void
    {
        $critter = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'crit' => 100]));
        $noCrit = new BattleUnit($this->combatant(['id' => 2, 'team' => 'A', 'crit' => 0]));
        $target = new BattleUnit($this->combatant(['id' => 3, 'team' => 'B', 'defense' => 0]));

        $calc = new DamageCalculator(new SeededRng(1));
        $crit = $calc->resolve(100, $critter, $target, 0);
        $plain = $calc->resolve(100, $noCrit, $target, 0);

        $this->assertTrue($crit['crit']);
        $this->assertFalse($plain['crit']);
        $this->assertSame(150, $crit['damage']);
        $this->assertSame(100, $plain['damage']);
    }

    public function test_assassin_targets_the_weakest_backline_enemy(): void
    {
        $assassin = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'class' => 'assassin']));
        $frontTank = new BattleUnit($this->combatant(['id' => 2, 'team' => 'B', 'position' => 'front', 'hp' => 300]));
        $backMage = new BattleUnit($this->combatant(['id' => 3, 'team' => 'B', 'position' => 'back', 'hp' => 60]));
        $backHealer = new BattleUnit($this->combatant(['id' => 4, 'team' => 'B', 'position' => 'back', 'hp' => 90]));

        $units = [$assassin, $frontTank, $backMage, $backHealer];
        $target = (new TargetSelector)->enemyTarget($assassin, $units, 0);

        $this->assertSame(3, $target->id()); // back, lowest hp
    }

    public function test_taunt_overrides_the_class_strategy(): void
    {
        $assassin = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'class' => 'assassin']));
        $tank = new BattleUnit($this->combatant(['id' => 2, 'team' => 'B', 'position' => 'front', 'hp' => 300]));
        $mage = new BattleUnit($this->combatant(['id' => 3, 'team' => 'B', 'position' => 'back', 'hp' => 50]));

        $assassin->taunt(by: 2, until: 5000);
        $target = (new TargetSelector)->enemyTarget($assassin, [$assassin, $tank, $mage], 1000);

        $this->assertSame(2, $target->id());
    }

    public function test_default_targeting_hits_the_nearest_enemy(): void
    {
        $fighter = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A']));
        $back = new BattleUnit($this->combatant(['id' => 2, 'team' => 'B', 'position' => 'back']));
        $front = new BattleUnit($this->combatant(['id' => 3, 'team' => 'B', 'position' => 'front']));

        $target = (new TargetSelector)->enemyTarget($fighter, [$fighter, $back, $front], 0);

        $this->assertSame(3, $target->id());
    }

    public function test_lowest_hp_ally_is_the_heal_target(): void
    {
        $healer = new BattleUnit($this->combatant(['id' => 1, 'team' => 'A', 'hp' => 100]));
        $hurt = new BattleUnit($this->combatant(['id' => 2, 'team' => 'A', 'hp' => 100]));
        $hurt->takeDamage(70);

        $target = (new TargetSelector)->lowestHpAlly($healer, [$healer, $hurt]);

        $this->assertSame(2, $target->id());
    }
}
