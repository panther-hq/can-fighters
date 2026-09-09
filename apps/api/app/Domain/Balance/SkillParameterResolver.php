<?php

namespace App\Domain\Balance;

/**
 * Turns a skill family + level (+ the owner's stats) into concrete parameters.
 * Baselines live in config/balance.php; offensive skills scale their power with
 * the fighter's best offence stat so a mage's blast hits harder than a
 * support's (spec §17).
 */
class SkillParameterResolver
{
    /**
     * @param  array{attack?: int, magic?: int}|null  $stats
     * @return array<string, int>
     */
    public function resolve(string $family, int $level, ?array $stats = null): array
    {
        $params = config("balance.skill_defaults.{$family}", ['power' => 10, 'cooldown' => 3]);
        $growth = (float) config('balance.skill_power_growth');

        if (isset($params['power'])) {
            $base = (float) $params['power'];

            if ($stats !== null && in_array($family, config('balance.offensive_skills'), true)) {
                $offense = max((int) ($stats['attack'] ?? 0), (int) ($stats['magic'] ?? 0));
                $base = $base * (float) config('balance.skill_offense_base')
                    + $offense * (float) config('balance.skill_offense_ratio');
            }

            $params['power'] = (int) round($base * (1 + $growth * max(0, $level - 1)));
        }

        return $params;
    }
}
