<?php

namespace App\Support;

/**
 * Small deterministic PRNG (Numerical Recipes LCG). Same seed -> same stream,
 * independent of PHP's global RNG. Used wherever an outcome must be
 * reproducible for replays / debugging (spec §11, §35).
 */
final class SeededRng
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0x7FFFFFFF;
        if ($this->state === 0) {
            $this->state = 1;
        }
    }

    public function next(): int
    {
        $this->state = ($this->state * 1664525 + 1013904223) & 0x7FFFFFFF;

        return $this->state;
    }

    /** 0 .. $maxExclusive - 1 */
    public function int(int $maxExclusive): int
    {
        return $maxExclusive > 0 ? $this->next() % $maxExclusive : 0;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    public function pick(array $items)
    {
        return $items[$this->int(count($items))];
    }

    public function chance(int $percent): bool
    {
        return $this->int(100) < $percent;
    }
}
