<?php

namespace Tests\Feature\Equipment;

use App\Domain\Equipment\EquipmentRoller;
use App\Models\EquipmentDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentRollerTest extends TestCase
{
    use RefreshDatabase;

    private function definition(): EquipmentDefinition
    {
        return EquipmentDefinition::firstWhere('slug', 'can-armor');
    }

    public function test_the_same_seed_rolls_the_same_stats(): void
    {
        $roller = app(EquipmentRoller::class);
        $user = User::factory()->create();

        $a = $roller->roll($user, $this->definition(), 'rare', 42);
        $b = $roller->roll($user, $this->definition(), 'rare', 42);

        $this->assertSame($a->rolled_stats, $b->rolled_stats);
    }

    public function test_higher_rarity_rolls_bigger_stats(): void
    {
        $roller = app(EquipmentRoller::class);
        $user = User::factory()->create();

        $common = $roller->roll($user, $this->definition(), 'common', 7);
        $legendary = $roller->roll($user, $this->definition(), 'legendary', 7);

        $this->assertGreaterThan($common->rolled_stats['hp'], $legendary->rolled_stats['hp']);
        $this->assertGreaterThan($common->rolled_stats['defense'], $legendary->rolled_stats['defense']);
    }
}
