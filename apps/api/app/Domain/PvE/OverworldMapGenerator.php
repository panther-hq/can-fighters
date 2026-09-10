<?php

namespace App\Domain\PvE;

use App\Models\RegionDefinition;
use App\Support\SeededRng;

/**
 * Builds a region's explorable tile map from a region + seed (spec §61, Heroes-3
 * style): a grass grid sprinkled with impassable rock/water, a hero start in one
 * corner, a boss in the far corner, a carved path guaranteeing the boss is
 * reachable, and roaming enemies / treasure / "?" events scattered on the
 * reachable tiles. Same seed -> same map.
 */
class OverworldMapGenerator
{
    /**
     * @return array<string, mixed>
     */
    public function generate(RegionDefinition $region, int $seed): array
    {
        $cfg = config('regions.overworld');
        $w = (int) $cfg['width'];
        $h = (int) $cfg['height'];
        $rng = new SeededRng($seed);

        $terrain = $this->terrain($rng, $w, $h, $cfg);

        $start = $this->pickCorner($rng, $terrain, $w, $h, near: true);
        $boss = $this->pickCorner($rng, $terrain, $w, $h, near: false, avoid: $start);
        $this->carve($terrain, $w, $start, $boss);

        $reachable = $this->floodFill($terrain, $w, $h, $start);
        // Guarantee the terrain is walkable everywhere the objects will sit.
        $free = [];
        foreach ($reachable as $key => $_) {
            [$x, $y] = array_map('intval', explode(',', $key));
            if ([$x, $y] !== $start && [$x, $y] !== $boss) {
                $free[] = [$x, $y];
            }
        }
        $free = $this->shuffle($rng, $free);

        $objects = [];
        $objects[] = $this->bossObject($rng, $region, $boss, $start, $cfg);

        $counts = $cfg['objects'];
        $take = function (int $n) use (&$free): array {
            return array_splice($free, 0, $n);
        };

        foreach ($take((int) $counts['enemies']) as $tile) {
            $objects[] = $this->enemyObject($rng, $region, $tile, $start, $cfg, count($objects));
        }
        foreach ($take((int) $counts['treasures']) as $tile) {
            $objects[] = $this->treasureObject($rng, $region, $tile, count($objects));
        }
        foreach ($take((int) $counts['events']) as $tile) {
            $objects[] = ['id' => 'o'.count($objects), 'x' => $tile[0], 'y' => $tile[1], 'kind' => 'event'];
        }

        return [
            'regionSlug' => $region->slug,
            'seed' => $seed,
            'width' => $w,
            'height' => $h,
            'terrain' => $terrain,
            'start' => ['x' => $start[0], 'y' => $start[1]],
            'objects' => $objects,
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    private function terrain(SeededRng $rng, int $w, int $h, array $cfg): array
    {
        $terrain = array_fill(0, $w * $h, 'grass');
        $rocks = intdiv($w * $h * (int) $cfg['obstacle_density'], 100);
        $water = intdiv($w * $h * (int) $cfg['water_density'], 100);

        $blob = function (string $type, int $budget) use (&$terrain, $rng, $w, $h): void {
            while ($budget > 0) {
                $cx = $rng->int($w);
                $cy = $rng->int($h);
                $size = 1 + $rng->int(3);
                for ($i = 0; $i < $size && $budget > 0; $i++) {
                    $x = min($w - 1, max(0, $cx + $rng->int(3) - 1));
                    $y = min($h - 1, max(0, $cy + $rng->int(3) - 1));
                    $idx = $y * $w + $x;
                    if ($terrain[$idx] === 'grass') {
                        $terrain[$idx] = $type;
                        $budget--;
                    }
                }
            }
        };

        $blob('rock', $rocks);
        $blob('water', $water);

        return $terrain;
    }

    /**
     * @param  list<string>  $terrain
     * @param  array{0: int, 1: int}|null  $avoid
     * @return array{0: int, 1: int}
     */
    private function pickCorner(SeededRng $rng, array $terrain, int $w, int $h, bool $near, ?array $avoid = null): array
    {
        $band = 3;
        $candidates = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($terrain[$y * $w + $x] !== 'grass') {
                    continue;
                }
                $inCorner = $near
                    ? ($x < $band && $y < $band)
                    : ($x >= $w - $band && $y >= $h - $band);
                if ($inCorner && [$x, $y] !== $avoid) {
                    $candidates[] = [$x, $y];
                }
            }
        }
        if ($candidates === []) {
            // Fall back to the literal corner, forced to grass by the caller's carve.
            return $near ? [0, 0] : [$w - 1, $h - 1];
        }

        return $candidates[$rng->int(count($candidates))];
    }

    /**
     * Clears a stair-step corridor between two tiles so the boss is always
     * reachable no matter how the blobs fell.
     *
     * @param  list<string>  $terrain
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     */
    private function carve(array &$terrain, int $w, array $from, array $to): void
    {
        [$x, $y] = $from;
        $terrain[$y * $w + $x] = 'grass';
        while ([$x, $y] !== $to) {
            if ($x !== $to[0]) {
                $x += $to[0] <=> $x;
            } elseif ($y !== $to[1]) {
                $y += $to[1] <=> $y;
            }
            $terrain[$y * $w + $x] = 'grass';
        }
    }

    /**
     * @param  list<string>  $terrain
     * @param  array{0: int, 1: int}  $start
     * @return array<string, true>
     */
    private function floodFill(array $terrain, int $w, int $h, array $start): array
    {
        $seen = [];
        $queue = [$start];
        $seen[$start[0].','.$start[1]] = true;

        while ($queue !== []) {
            [$x, $y] = array_shift($queue);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                    continue;
                }
                $key = $nx.','.$ny;
                if (isset($seen[$key])) {
                    continue;
                }
                $tile = $terrain[$ny * $w + $nx];
                if ($tile === 'rock' || $tile === 'water') {
                    continue;
                }
                $seen[$key] = true;
                $queue[] = [$nx, $ny];
            }
        }

        return $seen;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $items
     * @return list<array{0: int, 1: int}>
     */
    private function shuffle(SeededRng $rng, array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $rng->int($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /**
     * @param  array{0: int, 1: int}  $tile
     * @param  array{0: int, 1: int}  $start
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function enemyObject(SeededRng $rng, RegionDefinition $region, array $tile, array $start, array $cfg, int $index): array
    {
        $tier = $this->tier($tile, $start, $cfg);
        $elite = $rng->chance((int) $cfg['elite_chance']);
        $budget = (int) $region->enemy_budget + $tier * (int) $cfg['budget_step_per_tier'];
        if ($elite) {
            $budget = (int) round($budget * (float) $cfg['elite_budget_multiplier']);
        }

        return [
            'id' => 'o'.$index,
            'x' => $tile[0],
            'y' => $tile[1],
            'kind' => 'enemy',
            'elite' => $elite,
            'tier' => $tier,
            'budget' => $budget,
            'enemies' => $this->pickEnemies($rng, $region->enemy_pool, $elite ? 3 : (2 + $rng->int(2))),
        ];
    }

    /**
     * @param  array{0: int, 1: int}  $tile
     * @param  array{0: int, 1: int}  $start
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function bossObject(SeededRng $rng, RegionDefinition $region, array $tile, array $start, array $cfg): array
    {
        $budget = (int) round(
            ((int) $region->enemy_budget + (int) $cfg['boss_tier'] * (int) $cfg['budget_step_per_tier'])
            * (float) $cfg['boss_budget_multiplier'],
        );

        return [
            'id' => 'boss',
            'x' => $tile[0],
            'y' => $tile[1],
            'kind' => 'boss',
            'tier' => (int) $cfg['boss_tier'],
            'budget' => $budget,
            'enemies' => array_merge(
                [['name' => $region->boss['name'], 'class' => $region->boss['class']]],
                $this->pickEnemies($rng, $region->enemy_pool, 2),
            ),
        ];
    }

    /**
     * @param  array{0: int, 1: int}  $tile
     * @return array<string, mixed>
     */
    private function treasureObject(SeededRng $rng, RegionDefinition $region, array $tile, int $index): array
    {
        $roll = $rng->int(100);
        if ($roll < 18) {
            $reward = ['type' => 'can', 'slug' => $region->drops['cans'][0] ?? 'rusty', 'qty' => 1];
        } elseif ($roll < 62) {
            $reward = ['type' => 'ingredient', 'slug' => $rng->pick($region->drops['ingredients']), 'qty' => 2 + $rng->int(3)];
        } else {
            $reward = ['type' => 'coins', 'amount' => 70 + $rng->int(120)];
        }

        return ['id' => 'o'.$index, 'x' => $tile[0], 'y' => $tile[1], 'kind' => 'treasure', 'reward' => $reward];
    }

    /**
     * @param  array{0: int, 1: int}  $tile
     * @param  array{0: int, 1: int}  $start
     * @param  array<string, mixed>  $cfg
     */
    private function tier(array $tile, array $start, array $cfg): int
    {
        $dist = max(abs($tile[0] - $start[0]), abs($tile[1] - $start[1]));

        return min((int) $cfg['max_tier'], intdiv($dist, (int) $cfg['tier_step']));
    }

    /**
     * @param  list<array{name: string, class: string}>  $pool
     * @return list<array{name: string, class: string}>
     */
    private function pickEnemies(SeededRng $rng, array $pool, int $count): array
    {
        $picked = [];
        for ($i = 0; $i < $count && $pool !== []; $i++) {
            $picked[] = $pool[$rng->int(count($pool))];
        }

        return $picked;
    }
}
