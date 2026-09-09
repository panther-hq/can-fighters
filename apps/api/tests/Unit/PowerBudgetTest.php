<?php

namespace Tests\Unit;

use App\Domain\Balance\SkillParameterResolver;
use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class PowerBudgetTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    public static function fighterMatrix(): array
    {
        $cases = [];
        foreach (['tank', 'fighter', 'assassin', 'ranged', 'mage', 'support', 'debuffer', 'engineer'] as $class) {
            foreach (['common', 'rare', 'legendary'] as $rarity) {
                foreach ([1, 5, 10] as $level) {
                    $cases["{$class} {$rarity} L{$level}"] = [$class, $rarity, $level];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('fighterMatrix')]
    public function test_power_score_tracks_the_budget(string $class, string $rarity, int $level): void
    {
        $fighter = $this->makeFighter(User::factory()->create(), [
            'primary_class' => $class,
            'rarity' => $rarity,
            'level' => $level,
        ]);
        $stats = $fighter->stats;

        $this->assertGreaterThan(0, $stats->budget);
        // Normalisation should land power_score on the budget within a few %.
        $this->assertEqualsWithDelta($stats->budget, $stats->power_score, $stats->budget * 0.06);
        $this->assertTrue($stats->pvp_legal);
    }

    public function test_rarity_bonus_is_exactly_the_configured_edge(): void
    {
        $common = $this->makeFighter(User::factory()->create(), ['primary_class' => 'fighter', 'rarity' => 'common'])->stats;
        $legendary = $this->makeFighter(User::factory()->create(), ['primary_class' => 'fighter', 'rarity' => 'legendary'])->stats;

        $this->assertSame(100, $common->budget);
        $this->assertSame(135, $legendary->budget); // 100 + 35 (config)
    }

    public function test_an_over_budget_stat_block_is_flagged_illegal(): void
    {
        $engine = app(StandardBalanceEngine::class);

        $this->assertTrue($engine->isPvpLegal(105, 100));
        $this->assertTrue($engine->isPvpLegal(110, 100)); // exactly at tolerance
        $this->assertFalse($engine->isPvpLegal(140, 100));
    }

    public function test_recompute_after_manual_tampering_restores_legality(): void
    {
        $fighter = $this->makeFighter(User::factory()->create());
        $fighter->stats->update(['hp' => 9999, 'power_score' => 5000, 'pvp_legal' => true]);

        app(StandardBalanceEngine::class)->apply($fighter->fresh(['skills']));

        $stats = $fighter->fresh()->stats;
        $this->assertLessThan(400, $stats->hp);
        $this->assertTrue($stats->pvp_legal);
    }

    public function test_offensive_skill_power_scales_with_the_wielders_offence(): void
    {
        $mage = $this->makeFighter(User::factory()->create(), ['primary_class' => 'mage']);
        $support = $this->makeFighter(User::factory()->create(), ['primary_class' => 'support']);

        $magePower = $mage->skills->firstWhere('skill_family', 'direct_damage')->parameters['power'];
        $supportPower = $support->skills->firstWhere('skill_family', 'direct_damage')->parameters['power'];

        $this->assertGreaterThan($supportPower, $magePower);
    }

    public function test_skill_power_grows_with_skill_level(): void
    {
        $resolver = app(SkillParameterResolver::class);

        $l1 = $resolver->resolve('direct_damage', 1)['power'];
        $l4 = $resolver->resolve('direct_damage', 4)['power'];

        $this->assertGreaterThan($l1, $l4);
    }

    public function test_budget_grows_with_level(): void
    {
        $engine = app(StandardBalanceEngine::class);
        $l1 = Fighter::factory()->make(['rarity' => 'common', 'level' => 1]);
        $l10 = Fighter::factory()->make(['rarity' => 'common', 'level' => 10]);

        $this->assertGreaterThan($engine->budget($l1), $engine->budget($l10));
    }
}
