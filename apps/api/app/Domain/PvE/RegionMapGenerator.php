<?php

namespace App\Domain\PvE;

use App\Models\RegionDefinition;
use App\Support\SeededRng;

/**
 * Builds a run's branching node graph from a region + seed (spec §61 map,
 * roguelike style): choice rows of battle / elite / loot / merchant / event
 * nodes, a boss on top, edges connecting rows by column proximity.
 */
class RegionMapGenerator
{
    /**
     * @return array<string, mixed>
     */
    public function generate(RegionDefinition $region, int $seed): array
    {
        $rng = new SeededRng($seed);
        $choiceRows = (int) config('regions.choice_rows');
        $step = (int) config('regions.budget_step_per_row');

        $rows = [];
        for ($r = 0; $r < $choiceRows; $r++) {
            $count = $rng->pick(config('regions.nodes_per_row'));
            $weights = config("regions.row_weights.{$r}", ['battle' => 1]);
            $nodes = [];

            for ($c = 0; $c < $count; $c++) {
                $type = $this->weightedPick($rng, $weights);
                $node = ['id' => "{$r}-{$c}", 'row' => $r, 'col' => $c, 'type' => $type, 'edges' => []];

                if ($type === 'battle' || $type === 'elite') {
                    $budget = $region->enemy_budget + $r * $step;
                    if ($type === 'elite') {
                        $budget = (int) round($budget * (float) config('regions.elite_budget_multiplier'));
                    }
                    $node['budget'] = $budget;
                    $node['enemies'] = $this->pickEnemies($rng, $region->enemy_pool, $type === 'elite' ? 3 : ($rng->chance(50) ? 3 : 2));
                }

                $nodes[] = $node;
            }
            $rows[] = ['row' => $r, 'nodes' => $nodes];
        }

        $bossBudget = (int) round(
            ($region->enemy_budget + $choiceRows * $step) * (float) config('regions.boss_budget_multiplier'),
        );
        $rows[] = ['row' => $choiceRows, 'nodes' => [[
            'id' => 'boss',
            'row' => $choiceRows,
            'col' => 0,
            'type' => 'boss',
            'edges' => [],
            'budget' => $bossBudget,
            'enemies' => array_merge(
                [['name' => $region->boss['name'], 'class' => $region->boss['class']]],
                $this->pickEnemies($rng, $region->enemy_pool, 2),
            ),
        ]]];

        $this->connect($rng, $rows, $choiceRows);

        return ['regionSlug' => $region->slug, 'seed' => $seed, 'rows' => $rows];
    }

    /**
     * @param  array<int, array{row: int, nodes: list<array<string, mixed>>}>  $rows
     */
    private function connect(SeededRng $rng, array &$rows, int $choiceRows): void
    {
        for ($r = 0; $r < $choiceRows; $r++) {
            $cur = &$rows[$r]['nodes'];
            $next = $rows[$r + 1]['nodes'];
            $nextCount = count($next);
            $curCount = count($cur);

            foreach ($cur as &$node) {
                $mapped = $nextCount === 1
                    ? 0
                    : (int) round($node['col'] * ($nextCount - 1) / max(1, $curCount - 1));
                $targets = [$mapped];
                if ($nextCount > 1 && $rng->chance(45)) {
                    $targets[] = max(0, min($nextCount - 1, $mapped + ($rng->chance(50) ? 1 : -1)));
                }
                $node['edges'] = array_values(array_unique(
                    array_map(fn ($t) => $next[$t]['id'], $targets),
                ));
            }
            unset($node);

            foreach ($next as $j => $target) {
                $hasIncoming = false;
                foreach ($cur as $node) {
                    if (in_array($target['id'], $node['edges'], true)) {
                        $hasIncoming = true;
                        break;
                    }
                }
                if (! $hasIncoming) {
                    $closest = 0;
                    $best = PHP_INT_MAX;
                    foreach ($cur as $k => $node) {
                        $dist = abs($node['col'] - $j);
                        if ($dist < $best) {
                            $best = $dist;
                            $closest = $k;
                        }
                    }
                    $cur[$closest]['edges'][] = $target['id'];
                    $cur[$closest]['edges'] = array_values(array_unique($cur[$closest]['edges']));
                }
            }
            unset($cur);
        }
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weightedPick(SeededRng $rng, array $weights): string
    {
        $total = array_sum($weights);
        $roll = $rng->int(max(1, $total));
        $acc = 0;
        foreach ($weights as $key => $weight) {
            $acc += $weight;
            if ($roll < $acc) {
                return $key;
            }
        }

        return array_key_first($weights);
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
