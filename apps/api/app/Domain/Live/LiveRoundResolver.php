<?php

namespace App\Domain\Live;

use App\Support\SeededRng;

/**
 * Resolves one round of a live battle: two chosen actions, applied in speed
 * order, then upkeep (DoT, effect expiry, energy regen). Server-authoritative
 * and deterministic given the battle seed + round number (spec §32).
 *
 * A focused subset of the auto-battler's skill families: damage / heal /
 * shield / stun / slow / dot / buffs. Anything else acts as direct damage.
 */
class LiveRoundResolver
{
    private const CRIT_MULT = 1.5;

    private const DAMAGE_FAMILIES = ['direct_damage', 'area_damage', 'execute', 'lifesteal', 'bleed', 'poison'];

    /**
     * @param  array<string, mixed>  $state
     * @param  array<int, array{actorId: int, type: string, slot?: int, targetId?: int}>  $actions
     * @return array{state: array<string, mixed>, events: list<array<string, mixed>>, winner: ?string}
     */
    public function resolve(array $state, array $actions, int $round, int $seed): array
    {
        $rng = new SeededRng($seed + $round * 7919);
        $units = collect($state['units'])->keyBy('id')->all();
        $events = [];

        $ordered = collect($actions)
            ->sortByDesc(fn ($a) => ($units[$a['actorId']]['spd'] ?? 0) + ($units[$a['actorId']]['id'] ?? 0) / 1000)
            ->values();

        foreach ($ordered as $action) {
            $actor = &$units[$action['actorId']];
            if ($actor === null || $actor['hp'] <= 0) {
                continue;
            }
            if (($actor['stunUntilRound'] ?? 0) > $round) {
                $events[] = ['type' => 'stunned', 'source' => $actor['id']];

                continue;
            }

            $target = isset($action['targetId'], $units[$action['targetId']]) ? $units[$action['targetId']] : null;

            if ($action['type'] === 'skill') {
                $this->applySkill($actor, $action, $units, $round, $rng, $events);
            } else {
                $this->basicAttack($actor, $target, $units, $round, $rng, $events);
            }
            unset($actor);
        }

        foreach ($units as $id => &$unit) {
            if ($unit['hp'] <= 0) {
                continue;
            }
            foreach ($unit['effects'] ?? [] as $effect) {
                if (($effect['kind'] ?? '') === 'dot' && ($effect['untilRound'] ?? 0) > $round) {
                    $unit['hp'] = max(0, $unit['hp'] - (int) $effect['dmg']);
                    $events[] = ['type' => 'damage', 'target' => $id, 'damage' => (int) $effect['dmg'], 'cause' => $effect['label'] ?? 'dot'];
                    if ($unit['hp'] <= 0) {
                        $events[] = ['type' => 'death', 'target' => $id];
                    }
                }
            }
            $unit['effects'] = array_values(array_filter(
                $unit['effects'] ?? [],
                fn ($e) => ($e['untilRound'] ?? 0) > $round,
            ));
            $unit['energy'] = min(
                (int) config('live.energy_max'),
                (int) $unit['energy'] + (int) config('live.energy_per_round'),
            );
        }
        unset($unit);

        $state['units'] = array_values($units);
        $state['round'] = $round + 1;

        $aliveA = collect($units)->contains(fn ($u) => $u['team'] === 'A' && $u['hp'] > 0);
        $aliveB = collect($units)->contains(fn ($u) => $u['team'] === 'B' && $u['hp'] > 0);
        $winner = match (true) {
            $aliveA && ! $aliveB => 'A',
            $aliveB && ! $aliveA => 'B',
            ! $aliveA && ! $aliveB => 'draw',
            default => null,
        };

        return ['state' => $state, 'events' => $events, 'winner' => $winner];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<int, array<string, mixed>>  $units
     * @param  array{slot?: int, targetId?: int}  $action
     * @param  list<array<string, mixed>>  $events
     */
    private function applySkill(array &$actor, array $action, array &$units, int $round, SeededRng $rng, array &$events): void
    {
        $slot = (int) ($action['slot'] ?? 0);
        $skill = collect($actor['skills'])->firstWhere('slot', $slot);
        if ($skill === null) {
            return;
        }

        $family = $skill['family'];
        $params = $skill['params'] ?? [];
        $power = (int) ($params['power'] ?? 12);
        $duration = (int) ($params['duration'] ?? 2);
        $until = $round + max(1, $duration);

        $cooldowns = (array) ($actor['cooldowns'] ?? []);
        $cooldowns[(string) $slot] = $round + (int) ($params['cooldown'] ?? 2);
        $actor['cooldowns'] = $cooldowns;
        $actor['energy'] = max(0, (int) $actor['energy'] - LiveEnergy::cost($params));

        $events[] = ['type' => 'skill_used', 'source' => $actor['id'], 'skill' => $family, 'slot' => $slot];

        $target = isset($action['targetId'], $units[$action['targetId']]) ? $action['targetId'] : null;

        if (in_array($family, self::DAMAGE_FAMILIES, true) || ! $this->isSupportOrControl($family)) {
            if ($target !== null) {
                $this->dealDamage($actor, $units[$target], $power, $family, $round, $rng, $events);
            }

            return;
        }

        if (str_starts_with($family, 'buff_')) {
            $actor['effects'][] = [
                'kind' => 'stat',
                'stat' => substr($family, 5),
                'pct' => (int) ($params['amount'] ?? 20),
                'untilRound' => $until,
                'label' => $family,
            ];
            $events[] = ['type' => 'effect', 'target' => $actor['id'], 'effect' => $family];

            return;
        }

        match (true) {
            $family === 'heal' => $this->heal($units, $actor['team'], $power, $events),
            $family === 'shield' => $this->shield($units, $target ?? $actor['id'], $power, $until, $events),
            $family === 'stun' => $this->stun($units, $target, $until, (int) ($params['chance'] ?? 60), $rng, $events),
            $family === 'slow' => $this->statEffect($units, $target, 'spd', -(int) ($params['amount'] ?? 25), $until, 'slow', $events),
            in_array($family, ['poison', 'bleed'], true) => $this->dot($units, $target, max(1, (int) ($power / 2)), $until, $family, $events),
            str_starts_with($family, 'debuff_') => $this->statEffect($units, $target, substr($family, 7), -(int) ($params['amount'] ?? 20), $until, $family, $events),
            default => $target !== null ? $this->dealDamage($actor, $units[$target], $power, $family, $round, $rng, $events) : null,
        };
    }

    private function isSupportOrControl(string $family): bool
    {
        return in_array($family, [
            'heal', 'shield', 'stun', 'slow', 'silence', 'taunt', 'cleanse', 'revive',
            'poison', 'bleed',
        ], true) || str_starts_with($family, 'buff_') || str_starts_with($family, 'debuff_');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>|null  $target
     * @param  array<int, array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $events
     */
    private function basicAttack(array &$actor, ?array $target, array &$units, int $round, SeededRng $rng, array &$events): void
    {
        $enemyTeam = $actor['team'] === 'A' ? 'B' : 'A';
        if ($target === null || $target['hp'] <= 0 || $target['team'] === $actor['team']) {
            $target = collect($units)->first(fn ($u) => $u['team'] === $enemyTeam && $u['hp'] > 0);
        }
        if ($target === null) {
            return;
        }

        $raw = in_array($actor['class'], ['mage', 'support', 'debuffer', 'summoner'], true)
            ? (int) $actor['mag']
            : (int) $actor['atk'];

        $this->dealDamage($actor, $units[$target['id']], max(1, $raw), 'attack', $round, $rng, $events);
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $target
     * @param  list<array<string, mixed>>  $events
     */
    private function dealDamage(array $actor, array &$target, int $raw, string $cause, int $round, SeededRng $rng, array &$events): void
    {
        $defenseMod = 1.0;
        foreach ($target['effects'] ?? [] as $e) {
            if (($e['kind'] ?? '') === 'stat' && $e['stat'] === 'def' && ($e['untilRound'] ?? 0) > $round) {
                $defenseMod *= 1 + $e['pct'] / 100;
            }
        }
        $defense = max(0, (int) round($target['def'] * $defenseMod));
        $mitigation = 100 / (100 + $defense);
        $crit = $rng->int(100) < (int) $actor['crit'];
        $damage = max(1, (int) round($raw * $mitigation * ($crit ? self::CRIT_MULT : 1)));

        if (($target['shield'] ?? 0) > 0) {
            $absorbed = min((int) $target['shield'], $damage);
            $target['shield'] -= $absorbed;
            $damage -= $absorbed;
        }
        $target['hp'] = max(0, (int) $target['hp'] - $damage);

        $events[] = array_filter([
            'type' => 'damage',
            'source' => $actor['id'],
            'target' => $target['id'],
            'damage' => $damage,
            'cause' => $cause,
            'crit' => $crit ?: null,
        ], fn ($v) => $v !== null);

        if ($target['hp'] <= 0) {
            $events[] = ['type' => 'death', 'target' => $target['id']];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $events
     */
    private function heal(array &$units, string $team, int $power, array &$events): void
    {
        $hurt = collect($units)
            ->filter(fn ($u) => $u['team'] === $team && $u['hp'] > 0)
            ->sortBy(fn ($u) => $u['hp'] / max(1, $u['maxHp']))
            ->first();
        if ($hurt === null) {
            return;
        }
        $before = $units[$hurt['id']]['hp'];
        $units[$hurt['id']]['hp'] = min($hurt['maxHp'], $hurt['hp'] + $power);
        $events[] = ['type' => 'heal', 'target' => $hurt['id'], 'amount' => $units[$hurt['id']]['hp'] - $before];
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $events
     */
    private function shield(array &$units, int $targetId, int $power, int $until, array &$events): void
    {
        if (! isset($units[$targetId])) {
            return;
        }
        $units[$targetId]['shield'] = max((int) ($units[$targetId]['shield'] ?? 0), $power);
        $events[] = ['type' => 'effect', 'target' => $targetId, 'effect' => 'shield', 'until' => $until];
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $events
     */
    private function stun(array &$units, ?int $targetId, int $until, int $chance, SeededRng $rng, array &$events): void
    {
        if ($targetId === null || ! isset($units[$targetId])) {
            return;
        }
        $applied = $rng->int(100) < $chance;
        if ($applied) {
            $units[$targetId]['stunUntilRound'] = $until;
        }
        $events[] = ['type' => 'effect', 'target' => $targetId, 'effect' => 'stun', 'applied' => $applied];
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $events
     */
    private function statEffect(array &$units, ?int $targetId, string $stat, int $pct, int $until, string $label, array &$events): void
    {
        if ($targetId === null || ! isset($units[$targetId])) {
            return;
        }
        $units[$targetId]['effects'][] = ['kind' => 'stat', 'stat' => $stat, 'pct' => $pct, 'untilRound' => $until, 'label' => $label];
        $events[] = ['type' => 'effect', 'target' => $targetId, 'effect' => $label];
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @param  list<array<string, mixed>>  $events
     */
    private function dot(array &$units, ?int $targetId, int $dmg, int $until, string $label, array &$events): void
    {
        if ($targetId === null || ! isset($units[$targetId])) {
            return;
        }
        $units[$targetId]['effects'][] = ['kind' => 'dot', 'dmg' => $dmg, 'untilRound' => $until, 'label' => $label];
        $events[] = ['type' => 'effect', 'target' => $targetId, 'effect' => $label];
    }
}
