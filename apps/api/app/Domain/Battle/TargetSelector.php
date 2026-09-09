<?php

namespace App\Domain\Battle;

/**
 * Per-class targeting (spec §21). Soft rules, deterministic tie-breaks (id).
 */
class TargetSelector
{
    private const RANK = ['front' => 0, 'middle' => 1, 'back' => 2];

    /**
     * @param  list<BattleUnit>  $units
     */
    public function enemyTarget(BattleUnit $actor, array $units, int $time): ?BattleUnit
    {
        $enemies = $this->aliveEnemies($actor, $units);
        if ($enemies === []) {
            return null;
        }

        // A taunt overrides everything while the taunter lives.
        if ($actor->tauntedBy !== null && $time < $actor->tauntExpiresAt) {
            foreach ($enemies as $enemy) {
                if ($enemy->id() === $actor->tauntedBy) {
                    return $enemy;
                }
            }
        }

        return match ($actor->in->class) {
            'assassin' => $this->weakestBackline($enemies),
            'debuffer' => $this->biggestThreat($enemies, $time),
            'mage' => $this->nearest($enemies),
            default => $this->nearest($enemies),
        };
    }

    /**
     * @param  list<BattleUnit>  $units
     * @return list<BattleUnit>
     */
    public function enemyCluster(BattleUnit $actor, array $units, int $max, int $time): array
    {
        $enemies = $this->aliveEnemies($actor, $units);

        if ($actor->tauntedBy !== null && $time < $actor->tauntExpiresAt) {
            $forced = array_values(array_filter($enemies, fn ($e) => $e->id() === $actor->tauntedBy));
            if ($forced !== []) {
                return $forced;
            }
        }

        usort($enemies, $this->byPositionThenId(...));

        return array_slice($enemies, 0, max(1, $max));
    }

    /**
     * @param  list<BattleUnit>  $units
     */
    public function lowestHpAlly(BattleUnit $actor, array $units, bool $includeSelf = true): ?BattleUnit
    {
        $allies = array_values(array_filter(
            $units,
            fn (BattleUnit $u) => $u->team() === $actor->team()
                && $u->isAlive()
                && ($includeSelf || $u->id() !== $actor->id()),
        ));
        if ($allies === []) {
            return null;
        }

        usort($allies, fn ($a, $b) => [$a->hp / $a->maxHp, $a->id()] <=> [$b->hp / $b->maxHp, $b->id()]);

        return $allies[0];
    }

    /**
     * @param  list<BattleUnit>  $units
     */
    public function deadAlly(BattleUnit $actor, array $units): ?BattleUnit
    {
        foreach ($units as $unit) {
            if ($unit->team() === $actor->team() && ! $unit->isAlive()) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * @param  list<BattleUnit>  $units
     * @return list<BattleUnit>
     */
    private function aliveEnemies(BattleUnit $actor, array $units): array
    {
        return array_values(array_filter(
            $units,
            fn (BattleUnit $u) => $u->team() !== $actor->team() && $u->isAlive(),
        ));
    }

    /**
     * @param  list<BattleUnit>  $enemies
     */
    private function nearest(array $enemies): BattleUnit
    {
        usort($enemies, $this->byPositionThenId(...));

        return $enemies[0];
    }

    /**
     * @param  list<BattleUnit>  $enemies
     */
    private function weakestBackline(array $enemies): BattleUnit
    {
        usort($enemies, function (BattleUnit $a, BattleUnit $b) {
            return [-self::RANK[$a->in->position], $a->hp, $a->id()]
                <=> [-self::RANK[$b->in->position], $b->hp, $b->id()];
        });

        return $enemies[0];
    }

    /**
     * @param  list<BattleUnit>  $enemies
     */
    private function biggestThreat(array $enemies, int $time): BattleUnit
    {
        usort($enemies, function (BattleUnit $a, BattleUnit $b) use ($time) {
            return [-max($a->stat('attack', $time), $a->stat('magic', $time)), $a->id()]
                <=> [-max($b->stat('attack', $time), $b->stat('magic', $time)), $b->id()];
        });

        return $enemies[0];
    }

    private function byPositionThenId(BattleUnit $a, BattleUnit $b): int
    {
        return [self::RANK[$a->in->position], $a->id()] <=> [self::RANK[$b->in->position], $b->id()];
    }
}
