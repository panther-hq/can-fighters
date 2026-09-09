<?php

namespace App\Domain\Balance;

use App\Models\Fighter;

/**
 * Turns a fighter's class / rarity / level / skills into concrete stats and
 * skill parameters (spec §16). This is the ONLY place numbers are decided.
 * Phase 5 will tighten it (Power Budget enforcement, PvP legality); the
 * call sites and shape stay the same.
 */
class StandardBalanceEngine
{
    public function apply(Fighter $fighter): void
    {
        $fighter->loadMissing('skills');

        $stats = $this->stats($fighter);
        $fighter->stats()->updateOrCreate([], $stats);

        foreach ($fighter->skills as $skill) {
            $skill->update(['parameters' => $this->skillParameters($skill->skill_family, $skill->level)]);
        }
    }

    /**
     * @return array<string, int>
     */
    public function stats(Fighter $fighter): array
    {
        $budget = ((int) config('balance.base_power_budget'))
            + (int) (config('balance.rarity_budget_bonus')[$fighter->rarity] ?? 0);
        $budget *= 1 + ((float) config('balance.level_growth')) * max(0, $fighter->level - 1);
        $factor = $budget / 100;

        $profile = $this->profile($fighter->primary_class, $fighter->secondary_class);
        $scale = config('balance.scale');
        $floor = config('balance.floor');

        $out = [];
        foreach ($profile as $stat => $weight) {
            $out[$stat] = (int) round($floor[$stat] + $weight * $scale[$stat] * $factor);
        }
        $out['crit'] = min($out['crit'], (int) config('balance.crit_cap'));

        $power = 0.0;
        foreach (config('balance.power_weights') as $stat => $weight) {
            $power += $out[$stat] * $weight;
        }
        $out['power_score'] = (int) round($power);

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function skillParameters(string $family, int $level): array
    {
        $params = config("balance.skill_defaults.{$family}", ['power' => 10, 'cooldown' => 3]);
        $growth = (float) config('balance.skill_power_growth');

        if (isset($params['power'])) {
            $params['power'] = (int) round($params['power'] * (1 + $growth * max(0, $level - 1)));
        }

        return $params;
    }

    /**
     * @return array<string, float>
     */
    private function profile(string $primary, ?string $secondary): array
    {
        $profiles = config('balance.profiles');
        $profile = $profiles[$primary] ?? $profiles['fighter'];

        if ($secondary !== null && isset($profiles[$secondary])) {
            $blend = (float) config('balance.secondary_blend');
            foreach ($profile as $stat => $value) {
                $profile[$stat] = $value * (1 - $blend) + $profiles[$secondary][$stat] * $blend;
            }
        }

        return $profile;
    }
}
