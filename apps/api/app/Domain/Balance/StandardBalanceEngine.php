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
        $fighter->loadMissing(['skills', 'equipment.playerEquipment']);

        $stats = $this->stats($fighter);
        $stats = $this->withEquipment($stats, $fighter);
        $fighter->stats()->updateOrCreate([], $stats);

        foreach ($fighter->skills as $skill) {
            $skill->update([
                'parameters' => $this->skills->resolve($skill->skill_family, $skill->level, $stats),
            ]);
        }
    }

    /**
     * Gear adds flat bonuses on top of the budget-normalised block, so a
     * geared fighter is genuinely stronger (spec §23). `pvp_legal` keeps
     * tracking the base block — equipment does not make a fighter illegal.
     *
     * @param  array<string, int|bool>  $stats
     * @return array<string, int|bool>
     */
    private function withEquipment(array $stats, Fighter $fighter): array
    {
        foreach ($fighter->equipment as $slot) {
            foreach ($slot->playerEquipment->rolled_stats ?? [] as $key => $value) {
                if (isset($stats[$key])) {
                    $stats[$key] += (int) $value;
                }
            }
        }

        $stats['crit'] = min((int) $stats['crit'], (int) config('balance.crit_cap'));
        $stats['power_score'] = $this->powerScore($stats);

        return $stats;
    }

    /**
     * @return array{hp: int, attack: int, defense: int, magic: int, speed: int, crit: int, power_score: int, budget: int, pvp_legal: bool}
     */
    public function stats(Fighter $fighter): array
    {
        return $this->statsForBudget(
            $this->budget($fighter),
            $fighter->primary_class,
            $fighter->secondary_class,
        );
    }

    /**
     * Class profile -> stats normalised to a target budget. Used for fighters
     * and for PvE enemies (which are not Fighter models).
     *
     * @return array{hp: int, attack: int, defense: int, magic: int, speed: int, crit: int, power_score: int, budget: int, pvp_legal: bool}
     */
    public function statsForBudget(int $budget, string $primaryClass, ?string $secondaryClass = null): array
    {
        $raw = $this->rawStats($primaryClass, $secondaryClass);

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
    private function rawStats(string $primaryClass, ?string $secondaryClass): array
    {
        $profile = $this->profile($primaryClass, $secondaryClass);
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
