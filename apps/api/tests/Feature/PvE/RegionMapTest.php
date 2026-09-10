<?php

namespace Tests\Feature\PvE;

use App\Domain\PvE\OverworldMapGenerator;
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

    private function passable(array $map, int $x, int $y): bool
    {
        return ! in_array($map['terrain'][$y * $map['width'] + $x], ['rock', 'water'], true);
    }

    public function test_the_same_seed_generates_the_same_map(): void
    {
        $a = app(OverworldMapGenerator::class)->generate($this->kitchen(), 12345);
        $b = app(OverworldMapGenerator::class)->generate($this->kitchen(), 12345);

        $this->assertSame(json_encode($a), json_encode($b));
    }

    public function test_the_map_is_a_full_grid_with_a_passable_start(): void
    {
        $map = app(OverworldMapGenerator::class)->generate($this->kitchen(), 7);

        $this->assertSame($map['width'] * $map['height'], count($map['terrain']));
        $this->assertTrue($this->passable($map, $map['start']['x'], $map['start']['y']));
    }

    public function test_the_boss_exists_and_is_reachable_from_the_start(): void
    {
        $map = app(OverworldMapGenerator::class)->generate($this->kitchen(), 99);
        $w = $map['width'];
        $h = $map['height'];

        $boss = collect($map['objects'])->firstWhere('kind', 'boss');
        $this->assertNotNull($boss);
        $this->assertNotEmpty($boss['enemies']);

        // Flood fill from the start over passable tiles; the boss tile must be hit.
        $start = [$map['start']['x'], $map['start']['y']];
        $seen = [$start[0].','.$start[1] => true];
        $queue = [$start];
        while ($queue !== []) {
            [$cx, $cy] = array_shift($queue);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                    continue;
                }
                if (isset($seen[$nx.','.$ny]) || ! $this->passable($map, $nx, $ny)) {
                    continue;
                }
                $seen[$nx.','.$ny] = true;
                $queue[] = [$nx, $ny];
            }
        }

        $this->assertArrayHasKey($boss['x'].','.$boss['y'], $seen);
    }

    public function test_it_scatters_the_configured_object_counts(): void
    {
        $map = app(OverworldMapGenerator::class)->generate($this->kitchen(), 42);
        $counts = config('regions.overworld.objects');
        $by = collect($map['objects'])->groupBy('kind');

        $this->assertCount((int) $counts['enemies'], $by['enemy'] ?? collect());
        $this->assertCount((int) $counts['treasures'], $by['treasure'] ?? collect());
        $this->assertCount((int) $counts['events'], $by['event'] ?? collect());
        $this->assertCount(1, $by['boss']);

        // No two objects share a tile, and none sits on the start.
        $tiles = collect($map['objects'])->map(fn ($o) => $o['x'].','.$o['y']);
        $this->assertSame($tiles->count(), $tiles->unique()->count());
        $this->assertFalse($tiles->contains($map['start']['x'].','.$map['start']['y']));
    }

    public function test_enemy_and_boss_objects_carry_enemies_and_a_budget(): void
    {
        $map = app(OverworldMapGenerator::class)->generate($this->kitchen(), 2024);

        foreach ($map['objects'] as $object) {
            if (in_array($object['kind'], ['enemy', 'boss'], true)) {
                $this->assertNotEmpty($object['enemies']);
                $this->assertGreaterThan(0, $object['budget']);
            }
        }
    }
}
