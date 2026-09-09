<?php

namespace App\Domain\Arena;

/**
 * Standard Elo. Matchmaking widens by rating band, not raw power score
 * (spec §30).
 */
class Elo
{
    /**
     * @return array{attacker: int, defender: int}
     */
    public static function resolve(int $attacker, int $defender, bool $attackerWon): array
    {
        $k = (int) config('arena.k_factor');
        $min = (int) config('arena.min_rating');

        $expectedAttacker = 1 / (1 + 10 ** (($defender - $attacker) / 400));
        $scoreAttacker = $attackerWon ? 1.0 : 0.0;

        $deltaAttacker = (int) round($k * ($scoreAttacker - $expectedAttacker));

        return [
            'attacker' => max($min, $attacker + $deltaAttacker),
            'defender' => max($min, $defender - $deltaAttacker),
        ];
    }
}
