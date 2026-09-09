<?php

namespace Tests\Unit;

use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesFighters;
use Tests\TestCase;

class BalanceEngineTest extends TestCase
{
    use MakesFighters, RefreshDatabase;

    private function statsFor(string $class, array $attributes = []): object
    {
        $fighter = $this->makeFighter(User::factory()->create(), ['primary_class' => $class] + $attributes);

        return $fighter->stats;
    }

    public function test_class_profiles_have_the_expected_shape(): void
    {
        $tank = $this->statsFor('tank');
        $assassin = $this->statsFor('assassin');
        $mage = $this->statsFor('mage');

        $this->assertGreaterThan($assassin->hp, $tank->hp);
        $this->assertGreaterThan($tank->defense, $tank->hp);
        $this->assertGreaterThan($tank->speed, $assassin->speed);
        $this->assertGreaterThan($assassin->defense, $assassin->attack);
        $this->assertGreaterThan($mage->attack, $mage->magic);
    }

    public function test_rarity_is_a_small_edge_not_a_landslide(): void
    {
        $common = $this->statsFor('fighter', ['rarity' => 'common']);
        $legendary = $this->statsFor('fighter', ['rarity' => 'legendary']);

        $this->assertGreaterThan($common->power_score, $legendary->power_score);
        // "Rarity should provide an advantage, but not an overwhelming one." (§15)
        $this->assertLessThan($common->power_score * 1.6, $legendary->power_score);
    }

    public function test_levels_raise_stats(): void
    {
        $l1 = $this->statsFor('fighter', ['level' => 1]);
        $l5 = $this->statsFor('fighter', ['level' => 5]);

        $this->assertGreaterThan($l1->hp, $l5->hp);
        $this->assertGreaterThan($l1->power_score, $l5->power_score);
    }

    public function test_it_is_deterministic(): void
    {
        $user = User::factory()->create();
        $fighter = $this->makeFighter($user, ['primary_class' => 'mage', 'rarity' => 'rare', 'level' => 3]);
        $before = $fighter->stats->only(['hp', 'attack', 'defense', 'magic', 'speed', 'crit', 'power_score']);

        app(StandardBalanceEngine::class)->apply($fighter->fresh(['skills']));

        $this->assertSame($before, $fighter->fresh()->stats->only(array_keys($before)));
    }

    public function test_skill_parameters_are_populated_per_family(): void
    {
        $fighter = $this->makeFighter(User::factory()->create());

        $damage = $fighter->skills->firstWhere('skill_family', 'direct_damage');
        $shield = $fighter->skills->firstWhere('skill_family', 'shield');

        $this->assertArrayHasKey('power', $damage->parameters);
        $this->assertArrayHasKey('cooldown', $damage->parameters);
        $this->assertArrayHasKey('duration', $shield->parameters);
    }

    public function test_a_secondary_class_blends_the_profile(): void
    {
        $pureMage = $this->statsFor('mage');
        $mageTank = $this->makeFighter(User::factory()->create(), [
            'primary_class' => 'mage',
            'secondary_class' => 'tank',
        ])->stats;

        $this->assertGreaterThan($pureMage->hp, $mageTank->hp);
        $this->assertLessThan($pureMage->magic, $mageTank->magic);
    }

    public function test_apply_creates_the_stats_row_if_missing(): void
    {
        $fighter = Fighter::factory()->for(User::factory())->create();
        $this->assertNull($fighter->stats);

        app(StandardBalanceEngine::class)->apply($fighter);

        $this->assertNotNull($fighter->fresh()->stats);
    }
}
