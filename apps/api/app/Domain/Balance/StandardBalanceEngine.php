<?php

namespace App\Domain\Balance;

use App\Models\Fighter;

/**
 * Turns a fighter's class / rarity / level / skills into concrete stats and
 * skill parameters (spec §16). The ONLY place numbers are decided.
 *
 * Raw stats from the class weight profile are normalised so `power_score`
 * lands on the fighter's Power Budget (spec §15) — that makes rarity a small,
 * predictable edge and keeps classes comparable in total power.
 */
class StandardBalanceEngine
{
    private const STAT_KEYS = ['hp', 'attack', 'defense', 'magic', 'speed', 'crit'];

    public function __construct(private SkillParameterResolver $skills) {}

    public function apply(Fighter $fighter): void
    {
        $fighter->loadMissing('skills');

        $stats = $this->stats($fighter);
        $fighter->stats()->updateOrCreate([], $stats);

        foreach ($fighter->skills as $skill) {
            $skill->update([
                'parameters' => $this->skills->resolve($skill->skill_family, $skill->level, $stats),
            ]);
        }
    }

    /**
     * @return array{hp: int, attack: int, defense: int, magic: int, speed: int, crit: int, power_score: int, budget: int, pvp_legal: bool}
     */
    public function stats(Fighter $fighter): array
    {
        $budget = $this->budget($fighter);
        $raw = $this->rawStats($fighter);

        $rawPower = $this->powerScore($raw);
        $k = $rawPower > 0 ? $budget / $rawPower : 1.0;

        $floor = config('balance.floor');
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $out[$key] = max((int) $floor[$key], (int) round($raw[$key] * $k));
        }
        $out['crit'] = min($out['crit'], (int) config('balance.crit_cap'));

        $power = $this->powerScore($out);
        $out['power_score'] = $power;
        $out['budget'] = $budget;
        $out['pvp_legal'] = $this->isPvpLegal($power, $budget);

        return $out;
    }

    public function budget(Fighter $fighter): int
    {
        $base = ((int) config('balance.base_power_budget'))
            + (int) (config('balance.rarity_budget_bonus')[$fighter->rarity] ?? 0);

        return (int) round($base * (1 + ((float) config('balance.level_growth')) * max(0, $fighter->level - 1)));
    }

    public function isPvpLegal(int $powerScore, int $budget): bool
    {
        return $budget > 0 && $powerScore <= $budget * (float) config('balance.pvp_tolerance');
    }

    /**
     * @param  array<string, int|float>  $stats
     */
    public function powerScore(array $stats): int
    {
        $power = 0.0;
        foreach (config('balance.power_weights') as $key => $weight) {
            $power += ($stats[$key] ?? 0) * $weight;
        }

        return (int) round($power);
    }

    /**
     * @return array<string, float>
     */
    private function rawStats(Fighter $fighter): array
    {
        $profile = $this->profile($fighter->primary_class, $fighter->secondary_class);
        $scale = config('balance.scale');
        $floor = config('balance.floor');

        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $out[$key] = $floor[$key] + $profile[$key] * $scale[$key];
        }

        return $out;
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
