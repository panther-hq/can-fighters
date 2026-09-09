<?php

namespace Tests\Unit;

use App\Domain\Inventory\WeightedRoller;
use PHPUnit\Framework\TestCase;

class WeightedRollerTest extends TestCase
{
    public function test_same_seed_and_weights_produce_the_same_sequence(): void
    {
        $weights = ['a' => 10, 'b' => 5, 'c' => 1];

        $first = (new WeightedRoller($weights, 12345))->roll(20);
        $second = (new WeightedRoller($weights, 12345))->roll(20);

        $this->assertSame($first, $second);
        $this->assertCount(20, $first);
    }

    public function test_different_seeds_generally_differ(): void
    {
        $weights = ['a' => 3, 'b' => 3, 'c' => 3, 'd' => 3];

        $a = (new WeightedRoller($weights, 1))->roll(30);
        $b = (new WeightedRoller($weights, 2))->roll(30);

        $this->assertNotSame($a, $b);
    }

    public function test_only_ever_returns_known_slugs(): void
    {
        $weights = ['x' => 7, 'y' => 2, 'z' => 1];

        $picks = (new WeightedRoller($weights, 999))->roll(200);

        foreach ($picks as $pick) {
            $this->assertContains($pick, ['x', 'y', 'z']);
        }
    }

    public function test_a_zero_weight_slug_is_never_picked(): void
    {
        $weights = ['common' => 10, 'never' => 0];

        $picks = (new WeightedRoller($weights, 42))->roll(300);

        $this->assertNotContains('never', $picks);
    }
}
