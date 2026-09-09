<?php

namespace App\Domain\Inventory;

/**
 * Deterministic weighted picker. Same weights + seed always produce the same
 * sequence (spec §11, §35 — repeatable openings / replays), independent of
 * PHP's global RNG state.
 */
final class WeightedRoller
{
    private int $state;

    /** @var array<string, int> */
    private array $weights;

    /**
     * @param  array<string, int>  $weights  slug => weight
     */
    public function __construct(array $weights, int $seed)
    {
        ksort($weights); // stable iteration order
        $this->weights = $weights;
        $this->state = $seed & 0x7FFFFFFF;
    }

    /**
     * @return list<string> picked slugs, length $times
     */
    public function roll(int $times): array
    {
        $total = array_sum($this->weights);
        $picks = [];

        for ($i = 0; $i < $times; $i++) {
            $target = $this->next() % $total;
            $cursor = 0;
            foreach ($this->weights as $slug => $weight) {
                $cursor += $weight;
                if ($target < $cursor) {
                    $picks[] = $slug;
                    break;
                }
            }
        }

        return $picks;
    }

    /** Numerical Recipes LCG step. */
    private function next(): int
    {
        $this->state = ($this->state * 1664525 + 1013904223) & 0x7FFFFFFF;

        return $this->state;
    }
}
