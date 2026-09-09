<?php

namespace App\Domain\Battle;

use App\Support\SeededRng;

/**
 * Damage = raw × defence-mitigation × crit. Deterministic given the shared RNG.
 * MVP has one defence stat (spec §14), so physical and magic hits mitigate the
 * same way.
 */
class DamageCalculator
{
    private const CRIT_MULTIPLIER = 1.5;

    public function __construct(private SeededRng $rng) {}

    /**
     * @return array{damage: int, crit: bool}
     */
    public function resolve(int $raw, BattleUnit $attacker, BattleUnit $target, int $time): array
    {
        $defense = $target->stat('defense', $time);
        $mitigation = 100 / (100 + $defense);

        $crit = $this->rng->int(100) < $attacker->stat('crit', $time);
        $multiplier = $crit ? self::CRIT_MULTIPLIER : 1.0;

        $damage = max(1, (int) round($raw * $mitigation * $multiplier));

        return ['damage' => $damage, 'crit' => $crit];
    }
}
