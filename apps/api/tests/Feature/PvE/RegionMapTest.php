<?php

namespace Tests\Feature\PvE;

use App\Domain\PvE\RegionMapGenerator;
use App\Models\RegionDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionMapTest extends TestCase
{
    use RefreshDatabase;

    private function kitchen(): RegionDefinition
    {
        return RegionDefinition::firstWhere('slug', 'kitchen');
    }

    public function test_the_same_seed_generates_the_same_map(): void
    {
        $a = app(RegionMapGenerator::class)->generate($this->kitchen(), 12345);
        $b = app(RegionMapGenerator::class)->generate($this->kitchen(), 12345);

        $this->assertSame(json_encode($a), json_encode($b));
    }

    public function test_the_map_has_choice_rows_plus_a_boss_row(): void
    {
        $map = app(RegionMapGenerator::class)->generate($this->kitchen(), 7);
        $rows = $map['rows'];

        $this->assertCount((int) config('regions.choice_rows') + 1, $rows);

        $bossRow = end($rows);
        $this->assertCount(1, $bossRow['nodes']);
        $this->assertSame('boss', $bossRow['nodes'][0]['type']);
        $this->assertNotEmpty($bossRow['nodes'][0]['enemies']);
    }

    public function test_every_node_leads_forward_and_every_node_is_reachable(): void
    {
        $rows = app(RegionMapGenerator::class)->generate($this->kitchen(), 99)['rows'];

        for ($r = 0; $r < count($rows) - 1; $r++) {
            $nextIds = array_column($rows[$r + 1]['nodes'], 'id');

            foreach ($rows[$r]['nodes'] as $node) {
                $this->assertNotEmpty($node['edges'], "node {$node['id']} has no outgoing edge");
                foreach ($node['edges'] as $edge) {
                    $this->assertContains($edge, $nextIds);
                }
            }

            foreach ($rows[$r + 1]['nodes'] as $target) {
                $incoming = collect($rows[$r]['nodes'])->contains(fn ($n) => in_array($target['id'], $n['edges'], true));
                $this->assertTrue($incoming, "node {$target['id']} is unreachable");
            }
        }
    }

    public function test_battle_nodes_carry_enemies_and_a_budget(): void
    {
        $rows = app(RegionMapGenerator::class)->generate($this->kitchen(), 42)['rows'];

        foreach ($rows as $row) {
            foreach ($row['nodes'] as $node) {
                if (in_array($node['type'], ['battle', 'elite', 'boss'], true)) {
                    $this->assertNotEmpty($node['enemies']);
                    $this->assertGreaterThan(0, $node['budget']);
                }
            }
        }
    }
}
