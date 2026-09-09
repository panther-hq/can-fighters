<?php

namespace App\Domain\Battle;

use App\Domain\Battle\ValueObjects\BattleResult;
use App\Domain\Battle\ValueObjects\CombatantInput;
use App\Support\SeededRng;

/**
 * Simulates two teams to a result + an ordered event stream (spec §33).
 * Pure and deterministic: same combatants + seed => same battle, every time.
 * The frontend (Phaser) only replays `events`; it decides nothing.
 */
class BattleEngine
{
    public const VERSION = 1;

    private const MAX_TIME = 60_000;

    private const MAX_ACTIONS = 600;

    private const TURN_MS = 1000;

    public function __construct(private SkillResolver $skills) {}

    /**
     * @param  list<CombatantInput>  $combatants
     */
    public function run(array $combatants, int $seed): BattleResult
    {
        $rng = new SeededRng($seed);
        $units = array_map(fn (CombatantInput $c) => new BattleUnit($c), $combatants);

        $state = new BattleState($units, $rng, new DamageCalculator($rng), new TargetSelector);

        foreach ($units as $unit) {
            $unit->nextActAt = $unit->actionInterval(0);
            $state->emit('spawn', null, $unit->id(), [
                'team' => $unit->team(),
                'name' => $unit->in->name,
                'hp' => $unit->maxHp,
            ]);
        }
        $state->emit('battle_start');

        $actions = 0;
        while ($state->teamAlive('A') && $state->teamAlive('B') && $actions < self::MAX_ACTIONS) {
            $actor = $this->nextActor($units);
            if ($actor === null) {
                break;
            }

            if ($actor->nextActAt >= self::MAX_TIME) {
                $state->time = self::MAX_TIME;
                break; // time limit -> draw
            }

            $state->time = $actor->nextActAt;
            $actions++;

            $this->resolveDots($actor, $state);

            if ($actor->isAlive()) {
                if ($actor->isStunned($state->time)) {
                    $state->emit('stunned', null, $actor->id());
                } else {
                    $this->takeTurn($actor, $state);
                }
            }

            $actor->nextActAt = $state->time + $actor->actionInterval($state->time);
        }

        $winner = match (true) {
            $state->teamAlive('A') && ! $state->teamAlive('B') => 'A',
            $state->teamAlive('B') && ! $state->teamAlive('A') => 'B',
            default => 'draw',
        };
        $state->emit('battle_end', null, null, ['winner' => $winner]);

        return new BattleResult(
            winner: $winner,
            duration: $state->time,
            events: $state->events,
            survivorsA: $state->survivors('A'),
            survivorsB: $state->survivors('B'),
            seed: $seed,
            version: self::VERSION,
        );
    }

    /**
     * @param  list<BattleUnit>  $units
     */
    private function nextActor(array $units): ?BattleUnit
    {
        $best = null;
        foreach ($units as $unit) {
            if (! $unit->isAlive()) {
                continue;
            }
            if ($best === null
                || $unit->nextActAt < $best->nextActAt
                || ($unit->nextActAt === $best->nextActAt && $unit->id() < $best->id())
            ) {
                $best = $unit;
            }
        }

        return $best;
    }

    private function resolveDots(BattleUnit $actor, BattleState $state): void
    {
        foreach ($actor->tick($state->time) as $hit) {
            $state->fixedDamage($hit['source'], $actor, $hit['damage'], $hit['kind']);
            if (! $actor->isAlive()) {
                return;
            }
        }
    }

    private function takeTurn(BattleUnit $actor, BattleState $state): void
    {
        $skill = $this->readySkill($actor, $state);

        if ($skill !== null) {
            $state->emit('skill_used', $actor->id(), null, [
                'skill' => $skill['family'],
                'slot' => $skill['slot'],
            ]);
            $actor->cooldowns[$skill['slot']] = $state->time
                + (int) ($skill['parameters']['cooldown'] ?? 0) * self::TURN_MS;
            $this->skills->apply($actor, $skill, $state);

            return;
        }

        $this->basicAttack($actor, $state);
    }

    /**
     * @return array{slot: int, family: string, parameters: array<string, int>}|null
     */
    private function readySkill(BattleUnit $actor, BattleState $state): ?array
    {
        if ($actor->isSilenced($state->time)) {
            return null;
        }

        $skills = $actor->in->skills;
        usort($skills, fn ($a, $b) => $a['slot'] <=> $b['slot']);

        foreach ($skills as $skill) {
            if ($skill['family'] === 'counterattack') {
                continue; // passive — handled on being hit
            }
            if ($state->time >= ($actor->cooldowns[$skill['slot']] ?? 0)
                && $this->skillIsUseful($actor, $skill, $state)
            ) {
                return $skill;
            }
        }

        return null;
    }

    /**
     * Cheap "would this skill do anything right now?" check so the AI does not
     * waste a heal on a full team or a revive with no corpse.
     *
     * @param  array{family: string}  $skill
     */
    private function skillIsUseful(BattleUnit $actor, array $skill, BattleState $state): bool
    {
        return match ($skill['family']) {
            'heal', 'shield', 'cleanse' => $this->hasWoundedAlly($actor, $state),
            'revive' => $this->hasDeadAlly($actor, $state),
            default => true,
        };
    }

    private function hasWoundedAlly(BattleUnit $actor, BattleState $state): bool
    {
        foreach ($state->units as $unit) {
            if ($unit->team() === $actor->team() && $unit->isAlive() && $unit->hp * 100 < $unit->maxHp * 90) {
                return true;
            }
        }

        return false;
    }

    private function hasDeadAlly(BattleUnit $actor, BattleState $state): bool
    {
        foreach ($state->units as $unit) {
            if ($unit->team() === $actor->team() && ! $unit->isAlive()) {
                return true;
            }
        }

        return false;
    }

    private function basicAttack(BattleUnit $actor, BattleState $state): void
    {
        $target = $state->targets->enemyTarget($actor, $state->units, $state->time);
        if ($target === null) {
            return;
        }

        $state->hit($actor, $target, $actor->basicPower($state->time), ['cause' => 'attack']);
        $this->maybeCounter($target, $actor, $state);
    }

    private function maybeCounter(BattleUnit $defender, BattleUnit $attacker, BattleState $state): void
    {
        if (! $defender->isAlive() || ! $attacker->isAlive()) {
            return;
        }

        foreach ($defender->in->skills as $skill) {
            if ($skill['family'] !== 'counterattack') {
                continue;
            }
            $chance = (int) ($skill['parameters']['chance'] ?? 30);
            if ($state->rng->int(100) < $chance) {
                $power = (int) ($skill['parameters']['power'] ?? 10);
                $state->hit($defender, $attacker, $power, ['skill' => 'counterattack', 'cause' => 'counter']);
            }

            return;
        }
    }
}
