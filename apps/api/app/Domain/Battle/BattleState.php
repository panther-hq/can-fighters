<?php

namespace App\Domain\Battle;

use App\Domain\Battle\ValueObjects\BattleEvent;
use App\Support\SeededRng;

/**
 * Mutable simulation state: the units, the clock, the RNG and the ordered
 * event log the frontend will replay.
 */
class BattleState
{
    public int $time = 0;

    public int $sequence = 0;

    /** @var list<BattleEvent> */
    public array $events = [];

    /**
     * @param  list<BattleUnit>  $units
     */
    public function __construct(
        public array $units,
        public SeededRng $rng,
        public DamageCalculator $damage,
        public TargetSelector $targets,
    ) {}

    /**
     * @param  array<string, mixed>  $extra
     */
    public function emit(string $type, ?int $source = null, ?int $target = null, array $extra = []): void
    {
        $this->events[] = new BattleEvent(
            sequence: ++$this->sequence,
            time: $this->time,
            type: $type,
            source: $source,
            target: $target,
            extra: array_filter($extra, fn ($v) => $v !== null && $v !== false),
        );
    }

    public function teamAlive(string $team): bool
    {
        foreach ($this->units as $unit) {
            if ($unit->team() === $team && $unit->isAlive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function survivors(string $team): array
    {
        $ids = [];
        foreach ($this->units as $unit) {
            if ($unit->team() === $team && $unit->isAlive()) {
                $ids[] = $unit->id();
            }
        }

        return $ids;
    }

    /**
     * Resolve one instance of damage from $source onto $target and log it.
     *
     * @param  array{skill?: string, cause?: string}  $meta
     * @return array{damage: int, crit: bool, dealt: int}
     */
    public function hit(BattleUnit $source, BattleUnit $target, int $raw, array $meta = []): array
    {
        $resolved = $this->damage->resolve($raw, $source, $target, $this->time);
        $dealt = $target->takeDamage($resolved['damage']);

        $this->emit('damage', $source->id(), $target->id(), [
            'skill' => $meta['skill'] ?? null,
            'cause' => $meta['cause'] ?? 'attack',
            'damage' => $dealt,
            'crit' => $resolved['crit'],
        ]);

        if (! $target->isAlive()) {
            $this->emit('death', $source->id(), $target->id());
        }

        return $resolved + ['dealt' => $dealt];
    }

    /**
     * Fixed damage (dots, execute bonus) with no crit roll.
     */
    public function fixedDamage(?int $source, BattleUnit $target, int $amount, string $cause): void
    {
        $dealt = $target->takeDamage(max(1, $amount));
        $this->emit('damage', $source, $target->id(), ['cause' => $cause, 'damage' => $dealt]);

        if (! $target->isAlive()) {
            $this->emit('death', $source, $target->id());
        }
    }
}
