<?php

namespace App\Domain\Live;

class LiveEnergy
{
    /**
     * @param  array<string, int>  $skillParams
     */
    public static function cost(array $skillParams): int
    {
        $cooldown = (int) ($skillParams['cooldown'] ?? 2);

        return min(
            (int) config('live.energy_cost_cap'),
            max(1, $cooldown * (int) config('live.energy_cost_per_cooldown')),
        );
    }
}
