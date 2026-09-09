<?php

namespace App\Domain\Battle;

use App\Domain\Battle\ValueObjects\CombatantInput;

/**
 * A fighter's mutable state during a simulation. Nothing here is persisted —
 * the engine takes CombatantInput, runs, and returns events + a result.
 */
class BattleUnit
{
    private const MAGIC_CLASSES = ['mage', 'support', 'debuffer', 'summoner'];

    public int $hp;

    public readonly int $maxHp;

    public int $shield = 0;

    public int $shieldExpiresAt = 0;

    public int $stunnedUntil = 0;

    public int $silencedUntil = 0;

    public ?int $tauntedBy = null;

    public int $tauntExpiresAt = 0;

    public int $nextActAt = 0;

    /** @var array<int, int> slot => tick the skill is ready again */
    public array $cooldowns = [];

    /** @var list<array{stat: string, pct: int, expiresAt: int}> */
    public array $statMods = [];

    /** @var list<array{damage: int, expiresAt: int, nextTickAt: int, source: int, kind: string}> */
    public array $dots = [];

    public function __construct(public readonly CombatantInput $in)
    {
        $this->maxHp = max(1, $in->hp);
        $this->hp = $this->maxHp;
    }

    public function id(): int
    {
        return $this->in->id;
    }

    public function team(): string
    {
        return $this->in->team;
    }

    public function isAlive(): bool
    {
        return $this->hp > 0;
    }

    public function isStunned(int $time): bool
    {
        return $time < $this->stunnedUntil;
    }

    public function isSilenced(int $time): bool
    {
        return $time < $this->silencedUntil;
    }

    public function stat(string $name, int $time): int
    {
        $base = match ($name) {
            'attack' => $this->in->attack,
            'defense' => $this->in->defense,
            'magic' => $this->in->magic,
            'speed' => $this->in->speed,
            'crit' => $this->in->crit,
            default => 0,
        };

        $factor = 1.0;
        foreach ($this->statMods as $mod) {
            if ($mod['stat'] === $name && $time < $mod['expiresAt']) {
                $factor *= 1 + $mod['pct'] / 100;
            }
        }

        return max(0, (int) round($base * $factor));
    }

    public function basicPower(int $time): int
    {
        $stat = in_array($this->in->class, self::MAGIC_CLASSES, true) ? 'magic' : 'attack';

        return max(1, $this->stat($stat, $time));
    }

    public function actionInterval(int $time): int
    {
        return max(300, (int) round(1000 * 100 / (50 + $this->stat('speed', $time))));
    }

    public function takeDamage(int $amount): int
    {
        $amount = max(0, $amount);

        if ($this->shield > 0) {
            $absorbed = min($this->shield, $amount);
            $this->shield -= $absorbed;
            $amount -= $absorbed;
        }

        $before = $this->hp;
        $this->hp = max(0, $this->hp - $amount);

        return $before - $this->hp;
    }

    public function heal(int $amount): int
    {
        $before = $this->hp;
        $this->hp = min($this->maxHp, $this->hp + max(0, $amount));

        return $this->hp - $before;
    }

    public function addShield(int $amount, int $expiresAt): void
    {
        $this->shield = max($this->shield, $amount);
        $this->shieldExpiresAt = $expiresAt;
    }

    public function addStatMod(string $stat, int $pct, int $expiresAt): void
    {
        $this->statMods[] = ['stat' => $stat, 'pct' => $pct, 'expiresAt' => $expiresAt];
    }

    public function addDot(int $damage, int $expiresAt, int $source, string $kind, int $now): void
    {
        $this->dots[] = [
            'damage' => $damage,
            'expiresAt' => $expiresAt,
            'nextTickAt' => $now,
            'source' => $source,
            'kind' => $kind,
        ];
    }

    public function stun(int $until): void
    {
        $this->stunnedUntil = max($this->stunnedUntil, $until);
    }

    public function silence(int $until): void
    {
        $this->silencedUntil = max($this->silencedUntil, $until);
    }

    public function taunt(int $by, int $until): void
    {
        $this->tauntedBy = $by;
        $this->tauntExpiresAt = $until;
    }

    public function cleanseDebuffs(int $time): void
    {
        $this->statMods = array_values(array_filter(
            $this->statMods,
            fn ($mod) => $mod['pct'] >= 0 || $time >= $mod['expiresAt'],
        ));
        $this->dots = [];
        $this->stunnedUntil = min($this->stunnedUntil, $time);
        $this->silencedUntil = min($this->silencedUntil, $time);
    }

    /**
     * Expire timed effects and roll damage-over-time. Called once per this
     * unit's turn.
     *
     * @return list<array{damage: int, source: int, kind: string}>
     */
    public function tick(int $time): array
    {
        if ($this->shield > 0 && $time >= $this->shieldExpiresAt) {
            $this->shield = 0;
        }
        if ($this->tauntedBy !== null && $time >= $this->tauntExpiresAt) {
            $this->tauntedBy = null;
        }

        $this->statMods = array_values(array_filter(
            $this->statMods,
            fn ($mod) => $time < $mod['expiresAt'],
        ));

        $hits = [];
        $keep = [];
        foreach ($this->dots as $dot) {
            if ($time >= $dot['expiresAt']) {
                continue;
            }
            $hits[] = ['damage' => $dot['damage'], 'source' => $dot['source'], 'kind' => $dot['kind']];
            $keep[] = $dot;
        }
        $this->dots = $keep;

        return $hits;
    }
}
