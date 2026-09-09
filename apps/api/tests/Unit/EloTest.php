<?php

namespace Tests\Unit;

use App\Domain\Arena\Elo;
use App\Domain\Arena\LeagueTable;
use Tests\TestCase;

class EloTest extends TestCase
{
    public function test_beating_an_equal_opponent_gains_about_half_k(): void
    {
        $r = Elo::resolve(1000, 1000, attackerWon: true);

        $this->assertSame(1016, $r['attacker']);
        $this->assertSame(984, $r['defender']);
    }

    public function test_the_exchange_is_zero_sum(): void
    {
        $r = Elo::resolve(1200, 1000, attackerWon: false);

        $this->assertSame($r['attacker'] - 1200, -($r['defender'] - 1000));
        $this->assertLessThan(1200, $r['attacker']); // attacker lost, so lost rating
    }

    public function test_upsetting_a_much_stronger_opponent_gains_more(): void
    {
        $underdog = Elo::resolve(900, 1400, attackerWon: true)['attacker'] - 900;
        $expected = Elo::resolve(1400, 900, attackerWon: true)['attacker'] - 1400;

        $this->assertGreaterThan($expected, $underdog);
    }

    public function test_rating_never_drops_below_the_floor(): void
    {
        $r = Elo::resolve(100, 2000, attackerWon: false);

        $this->assertGreaterThanOrEqual((int) config('arena.min_rating'), $r['attacker']);
    }

    public function test_league_thresholds(): void
    {
        $this->assertSame('Brąz', LeagueTable::forRating(800));
        $this->assertSame('Srebro', LeagueTable::forRating(1000));
        $this->assertSame('Złoto', LeagueTable::forRating(1200));
        $this->assertSame('Puszkowa Legenda', LeagueTable::forRating(2000));
    }
}
