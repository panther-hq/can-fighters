<?php

namespace App\Domain\PvE;

use App\Domain\Economy\GrantReward;
use App\Models\PlayerRegionClear;
use App\Models\PlayerRegionRun;
use App\Models\RegionDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\SeededRng;
use Illuminate\Support\Facades\DB;

/**
 * Drives a Heroes-3-style region expedition: a tile map you walk a hero across
 * with a per-day movement budget and fog of war. Stepping onto a roaming enemy
 * starts a battle, onto a treasure grants loot, onto a "?" fires an event. A
 * lost battle ends the run; beating the boss clears the region.
 */
class RegionRun
{
    private const IMPASSABLE = ['rock', 'water'];

    public function __construct(
        private OverworldMapGenerator $generator,
        private RunBattle $runBattle,
        private GrantReward $grant,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function regions(User $user): array
    {
        $clears = PlayerRegionClear::query()->where('user_id', $user->id)->get()->keyBy('region_slug');
        $defs = RegionDefinition::query()->orderBy('order')->get();

        return $defs->map(function (RegionDefinition $region) use ($defs, $clears) {
            $previous = $defs->firstWhere('order', $region->order - 1);
            $unlocked = $region->order <= 1
                || ($previous !== null && (int) ($clears[$previous->slug]->times_cleared ?? 0) >= 1);

            return [
                'slug' => $region->slug,
                'name' => $region->name,
                'order' => $region->order,
                'unlocked' => $unlocked,
                'timesCleared' => (int) ($clears[$region->slug]->times_cleared ?? 0),
            ];
        })->all();
    }

    public function activeRun(User $user): ?PlayerRegionRun
    {
        return PlayerRegionRun::query()
            ->where('user_id', $user->id)
            ->where('status', PlayerRegionRun::STATUS_ACTIVE)
            ->latest()
            ->first();
    }

    public function start(User $user, string $regionSlug): PlayerRegionRun
    {
        $region = RegionDefinition::query()->where('slug', $regionSlug)->first();
        if ($region === null) {
            throw new StageLockedException('Nie ma takiego regionu.');
        }
        if (! collect($this->regions($user))->firstWhere('slug', $regionSlug)['unlocked']) {
            throw new StageLockedException('Ten region jest jeszcze zablokowany.');
        }
        $this->campaignTeam($user);

        return DB::transaction(function () use ($user, $region): PlayerRegionRun {
            PlayerRegionRun::query()
                ->where('user_id', $user->id)
                ->where('status', PlayerRegionRun::STATUS_ACTIVE)
                ->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);

            $seed = random_int(1, PHP_INT_MAX);
            $map = $this->generator->generate($region, $seed);
            $move = (int) config('regions.overworld.movement_per_day');

            $run = new PlayerRegionRun([
                'user_id' => $user->id,
                'region_slug' => $region->slug,
                'seed' => $seed,
                'map' => $map,
                'hero_x' => $map['start']['x'],
                'hero_y' => $map['start']['y'],
                'movement_left' => $move,
                'movement_max' => $move,
                'day' => 1,
                'revealed' => [],
                'resolved_object_ids' => [],
                'status' => PlayerRegionRun::STATUS_ACTIVE,
            ]);
            $run->revealed = $this->reveal($run, [[$map['start']['x'], $map['start']['y']]]);
            $run->save();

            return $run;
        });
    }

    public function abandon(PlayerRegionRun $run): void
    {
        if ($run->status === PlayerRegionRun::STATUS_ACTIVE) {
            $run->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);
        }
    }

    /**
     * Walk the hero toward (x, y) along the shortest passable path, spending one
     * movement point per tile. If the day's budget runs out first the hero stops
     * partway. Landing on an unresolved object resolves it.
     *
     * @return array<string, mixed>
     */
    public function move(User $user, PlayerRegionRun $run, int $x, int $y): array
    {
        $this->assertActive($run);
        if ($run->active_merchant !== null) {
            throw new StageLockedException('Najpierw opuść kupca.');
        }

        $map = $run->map;
        $w = (int) $map['width'];
        $h = (int) $map['height'];
        if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
            throw new StageLockedException('To pole jest poza mapą.');
        }
        if (in_array($map['terrain'][$y * $w + $x], self::IMPASSABLE, true)) {
            throw new StageLockedException('Tam nie da się wejść.');
        }
        if ($x === (int) $run->hero_x && $y === (int) $run->hero_y) {
            throw new StageLockedException('Już tu jesteś.');
        }

        $path = $this->findPath($run, [(int) $run->hero_x, (int) $run->hero_y], [$x, $y]);
        if ($path === null) {
            throw new StageLockedException('Nie ma drogi do tego pola.');
        }

        $steps = count($path) - 1;
        $budget = (int) $run->movement_left;

        if ($steps > $budget) {
            $partial = array_slice($path, 0, $budget + 1);
            $land = end($partial);
            $this->walkTo($run, $land, $partial);
            $run->update(['movement_left' => 0]);

            return ['type' => 'move', 'path' => $partial, 'run' => $this->view($run->fresh())];
        }

        $this->walkTo($run, [$x, $y], $path);
        $run->update(['movement_left' => $budget - $steps]);

        $object = $this->unresolvedObjectAt($run, $x, $y);
        if ($object === null) {
            return ['type' => 'move', 'path' => $path, 'run' => $this->view($run->fresh())];
        }

        $outcome = $this->resolveObject($user, $run->fresh(), $object);
        $outcome['path'] = $path;

        return $outcome;
    }

    /**
     * Refill the movement budget and drift every un-cleared roaming enemy one
     * tile (deterministic per seed + day), so the map feels alive between turns.
     *
     * @return array<string, mixed>
     */
    public function endDay(User $user, PlayerRegionRun $run): array
    {
        $this->assertActive($run);
        if ($run->active_merchant !== null) {
            throw new StageLockedException('Najpierw opuść kupca.');
        }

        $day = (int) $run->day + 1;
        $map = $run->map;
        $w = (int) $map['width'];
        $h = (int) $map['height'];
        $resolved = $run->resolved_object_ids ?? [];

        $occupied = [];
        foreach ($map['objects'] as $o) {
            if (! in_array($o['id'], $resolved, true)) {
                $occupied[$o['x'].','.$o['y']] = true;
            }
        }

        foreach ($map['objects'] as $i => $o) {
            if ($o['kind'] !== 'enemy' || in_array($o['id'], $resolved, true)) {
                continue;
            }
            $rng = new SeededRng((int) $run->seed + $day * 7919 + crc32((string) $o['id']));
            [$dx, $dy] = [[1, 0], [-1, 0], [0, 1], [0, -1]][$rng->int(4)];
            $nx = (int) $o['x'] + $dx;
            $ny = (int) $o['y'] + $dy;

            if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                continue;
            }
            if (in_array($map['terrain'][$ny * $w + $nx], self::IMPASSABLE, true)) {
                continue;
            }
            if (isset($occupied[$nx.','.$ny])) {
                continue;
            }
            if ($nx === (int) $run->hero_x && $ny === (int) $run->hero_y) {
                continue;
            }

            unset($occupied[$o['x'].','.$o['y']]);
            $occupied[$nx.','.$ny] = true;
            $map['objects'][$i]['x'] = $nx;
            $map['objects'][$i]['y'] = $ny;
        }

        $run->update([
            'day' => $day,
            'movement_left' => (int) $run->movement_max,
            'map' => $map,
        ]);

        return ['type' => 'day', 'run' => $this->view($run->fresh())];
    }

    /**
     * @return array<string, mixed>
     */
    public function buyFromMerchant(User $user, PlayerRegionRun $run, string $offerId): array
    {
        return DB::transaction(function () use ($user, $run, $offerId): array {
            $locked = PlayerRegionRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $merchant = $locked->active_merchant;
            if ($merchant === null) {
                throw new StageLockedException('Nie ma tu kupca.');
            }

            $index = collect($merchant['offers'])->search(fn ($o) => $o['id'] === $offerId);
            if ($index === false) {
                throw new StageLockedException('Nie ma takiej oferty.');
            }
            $offer = $merchant['offers'][$index];
            if ($offer['bought'] ?? false) {
                throw new StageLockedException('Ta oferta jest już wykupiona.');
            }

            $profile = $user->playerProfile()->lockForUpdate()->first();
            if ($profile->coins < $offer['price']) {
                throw new StageLockedException('Za mało monet.');
            }
            $profile->decrement('coins', $offer['price']);
            $reward = $this->grant->grant($user, $offer['grant']);

            $merchant['offers'][$index]['bought'] = true;
            $locked->update(['active_merchant' => $merchant]);

            return ['reward' => $reward, 'run' => $this->view($locked->fresh())];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function leaveMerchant(PlayerRegionRun $run): array
    {
        $merchant = $run->active_merchant;
        if ($merchant === null) {
            return ['run' => $this->view($run)];
        }
        $this->markResolved($run, $merchant['objectId']);
        $run->update(['active_merchant' => null]);

        return ['run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function resolveObject(User $user, PlayerRegionRun $run, array $object): array
    {
        $region = RegionDefinition::query()->where('slug', $run->region_slug)->firstOrFail();

        return match ($object['kind']) {
            'enemy', 'boss' => $this->resolveBattle($user, $run, $object, $region),
            'treasure' => $this->resolveTreasure($user, $run, $object),
            'event' => $this->resolveEvent($user, $run, $object, $region),
            default => throw new StageLockedException('Nieznany obiekt.'),
        };
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function resolveBattle(User $user, PlayerRegionRun $run, array $object, RegionDefinition $region): array
    {
        $team = $this->campaignTeam($user);
        $node = [
            'id' => $object['id'],
            'row' => (int) ($object['tier'] ?? 0),
            'type' => $object['kind'] === 'boss' ? 'boss' : (($object['elite'] ?? false) ? 'elite' : 'battle'),
            'budget' => (int) $object['budget'],
            'enemies' => $object['enemies'],
        ];
        $outcome = $this->runBattle->fight($user, $team, $node, $region);

        if (! $outcome['won']) {
            $run->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);

            return [
                'type' => 'battle',
                'won' => false,
                'runEnded' => true,
                'battleId' => $outcome['battle']->id,
                'result' => $outcome['result'],
                'run' => $this->view($run->fresh()),
            ];
        }

        $this->markResolved($run, $object['id']);
        if ($object['kind'] === 'boss') {
            $run->update(['status' => PlayerRegionRun::STATUS_CLEARED]);
            $this->recordClear($user, $region);
        }

        return [
            'type' => 'battle',
            'won' => true,
            'battleId' => $outcome['battle']->id,
            'result' => $outcome['result'],
            'rewards' => $outcome['rewards'],
            'run' => $this->view($run->fresh()),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function resolveTreasure(User $user, PlayerRegionRun $run, array $object): array
    {
        $reward = $this->grant->grant($user, $object['reward']);
        $this->markResolved($run, $object['id']);

        return ['type' => 'loot', 'rewards' => [$reward], 'run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function resolveEvent(User $user, PlayerRegionRun $run, array $object, RegionDefinition $region): array
    {
        $rng = new SeededRng((int) $run->seed + crc32('event'.$object['id']) + (int) $run->day);
        $outcome = $this->weightedPick($rng, config('regions.event_weights'));

        if ($outcome === 'skarb') {
            $rewards = [$this->grant->grant($user, ['type' => 'coins', 'amount' => 60 + $rng->int(80)])];
            if ($rng->chance(25)) {
                $rewards[] = $this->grant->grant($user, ['type' => 'can', 'slug' => $region->drops['cans'][0] ?? 'rusty', 'qty' => 1]);
            }
            $this->markResolved($run, $object['id']);

            return ['type' => 'event', 'event' => 'skarb', 'rewards' => $rewards, 'run' => $this->view($run->fresh())];
        }

        if ($outcome === 'trening') {
            $xp = 28 + (int) $run->day * 5;
            $this->runBattle->awardFighterXp($this->campaignTeam($user), $xp);
            $this->markResolved($run, $object['id']);

            return ['type' => 'event', 'event' => 'trening', 'rewards' => [['type' => 'fighterXp', 'amount' => $xp]], 'run' => $this->view($run->fresh())];
        }

        if ($outcome === 'handlarz') {
            return $this->openMerchant($run, $object, rare: true);
        }

        // pulapka
        if ($rng->chance(50)) {
            $lost = 40 + $rng->int(50);
            $user->playerProfile()->decrement('coins', min($lost, (int) $user->playerProfile->coins));
            $this->markResolved($run, $object['id']);

            return ['type' => 'event', 'event' => 'pulapka', 'rewards' => [['type' => 'coins', 'amount' => -$lost]], 'run' => $this->view($run->fresh())];
        }

        $team = $this->campaignTeam($user);
        $ambush = [
            'id' => $object['id'],
            'row' => 1,
            'type' => 'battle',
            'budget' => (int) round((int) $region->enemy_budget * 1.15),
            'enemies' => array_slice($region->enemy_pool, 0, 2),
        ];
        $outcome = $this->runBattle->fight($user, $team, $ambush, $region);
        if (! $outcome['won']) {
            $run->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);

            return ['type' => 'event', 'event' => 'zasadzka', 'won' => false, 'runEnded' => true, 'battleId' => $outcome['battle']->id, 'result' => $outcome['result'], 'run' => $this->view($run->fresh())];
        }
        $this->markResolved($run, $object['id']);

        return ['type' => 'event', 'event' => 'zasadzka', 'won' => true, 'battleId' => $outcome['battle']->id, 'result' => $outcome['result'], 'rewards' => $outcome['rewards'], 'run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function openMerchant(PlayerRegionRun $run, array $object, bool $rare = false): array
    {
        $region = RegionDefinition::query()->where('slug', $run->region_slug)->firstOrFail();
        $rng = new SeededRng((int) $run->seed + crc32('shop'.$object['id']));
        $offers = [];
        $count = $rare ? 1 : 3;

        for ($i = 0; $i < $count; $i++) {
            if ($i === 0 && ($rare || $rng->chance(45))) {
                $offers[] = [
                    'id' => "m{$i}",
                    'label' => 'Zardzewiała puszka',
                    'icon' => '🥫',
                    'price' => $rare ? 180 : 260,
                    'grant' => ['type' => 'can', 'slug' => $region->drops['cans'][0] ?? 'rusty', 'qty' => 1],
                    'bought' => false,
                ];

                continue;
            }
            $slug = $rng->pick($region->drops['ingredients']);
            $qty = 2 + $rng->int(3);
            $offers[] = [
                'id' => "m{$i}",
                'label' => "{$slug} ×{$qty}",
                'icon' => '🧪',
                'price' => 12 * $qty,
                'grant' => ['type' => 'ingredient', 'slug' => $slug, 'qty' => $qty],
                'bought' => false,
            ];
        }

        $run->update(['active_merchant' => ['objectId' => $object['id'], 'offers' => $offers]]);

        return ['type' => 'merchant', 'offers' => $offers, 'run' => $this->view($run->fresh())];
    }

    private function recordClear(User $user, RegionDefinition $region): void
    {
        $clear = PlayerRegionClear::query()->firstOrNew([
            'user_id' => $user->id,
            'region_slug' => $region->slug,
        ]);
        $clear->times_cleared = (int) $clear->times_cleared + 1;
        $clear->first_cleared_at ??= now();
        $clear->save();
    }

    /**
     * Breadth-first shortest path over passable tiles. Un-cleared objects block
     * transit but the destination tile itself is always allowed as the endpoint.
     *
     * @param  array{0: int, 1: int}  $from
     * @param  array{0: int, 1: int}  $to
     * @return list<array{0: int, 1: int}>|null
     */
    private function findPath(PlayerRegionRun $run, array $from, array $to): ?array
    {
        $map = $run->map;
        $w = (int) $map['width'];
        $h = (int) $map['height'];
        $blocked = $this->blockedTiles($run);
        $toKey = $to[0].','.$to[1];

        $queue = [$from];
        $prev = [$from[0].','.$from[1] => null];

        while ($queue !== []) {
            [$cx, $cy] = array_shift($queue);
            if ($cx === $to[0] && $cy === $to[1]) {
                break;
            }
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                    continue;
                }
                $key = $nx.','.$ny;
                if (array_key_exists($key, $prev)) {
                    continue;
                }
                if (in_array($map['terrain'][$ny * $w + $nx], self::IMPASSABLE, true)) {
                    continue;
                }
                if ($key !== $toKey && isset($blocked[$key])) {
                    continue;
                }
                $prev[$key] = [$cx, $cy];
                $queue[] = [$nx, $ny];
            }
        }

        if (! array_key_exists($toKey, $prev)) {
            return null;
        }

        $path = [];
        $cur = $to;
        while ($cur !== null) {
            $path[] = $cur;
            $cur = $prev[$cur[0].','.$cur[1]];
        }

        return array_reverse($path);
    }

    /**
     * @return array<string, true>
     */
    private function blockedTiles(PlayerRegionRun $run): array
    {
        $resolved = $run->resolved_object_ids ?? [];
        $blocked = [];
        foreach ($run->map['objects'] as $o) {
            if (! in_array($o['id'], $resolved, true)) {
                $blocked[$o['x'].','.$o['y']] = true;
            }
        }

        return $blocked;
    }

    /**
     * @param  array{0: int, 1: int}  $land
     * @param  list<array{0: int, 1: int}>  $path
     */
    private function walkTo(PlayerRegionRun $run, array $land, array $path): void
    {
        $run->update([
            'hero_x' => $land[0],
            'hero_y' => $land[1],
            'revealed' => $this->reveal($run, $path),
        ]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $cells
     * @return list<string>
     */
    private function reveal(PlayerRegionRun $run, array $cells): array
    {
        $map = $run->map;
        $w = (int) $map['width'];
        $h = (int) $map['height'];
        $radius = (int) config('regions.overworld.reveal_radius');

        $set = array_fill_keys($run->revealed ?? [], true);
        foreach ($cells as [$cx, $cy]) {
            for ($dy = -$radius; $dy <= $radius; $dy++) {
                for ($dx = -$radius; $dx <= $radius; $dx++) {
                    $nx = $cx + $dx;
                    $ny = $cy + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                        continue;
                    }
                    $set[$nx.','.$ny] = true;
                }
            }
        }

        return array_keys($set);
    }

    private function markResolved(PlayerRegionRun $run, string $objectId): void
    {
        $ids = $run->resolved_object_ids ?? [];
        $ids[] = $objectId;
        $run->update(['resolved_object_ids' => array_values(array_unique($ids))]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unresolvedObjectAt(PlayerRegionRun $run, int $x, int $y): ?array
    {
        $resolved = $run->resolved_object_ids ?? [];
        foreach ($run->map['objects'] as $o) {
            if ((int) $o['x'] === $x && (int) $o['y'] === $y && ! in_array($o['id'], $resolved, true)) {
                return $o;
            }
        }

        return null;
    }

    private function assertActive(PlayerRegionRun $run): void
    {
        if ($run->status !== PlayerRegionRun::STATUS_ACTIVE) {
            throw new StageLockedException('Ta wyprawa się zakończyła.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function view(PlayerRegionRun $run): array
    {
        $map = $run->map;
        $resolved = $run->resolved_object_ids ?? [];
        $revealed = array_fill_keys($run->revealed ?? [], true);

        $objects = [];
        foreach ($map['objects'] as $o) {
            if (in_array($o['id'], $resolved, true)) {
                continue;
            }
            if (! isset($revealed[$o['x'].','.$o['y']])) {
                continue;
            }
            $entry = [
                'id' => $o['id'],
                'x' => (int) $o['x'],
                'y' => (int) $o['y'],
                'kind' => $o['kind'],
            ];
            if ($o['kind'] === 'enemy' || $o['kind'] === 'boss') {
                $entry['elite'] = (bool) ($o['elite'] ?? false);
                $entry['budget'] = (int) $o['budget'];
                $entry['enemies'] = $o['enemies'];
            }
            $objects[] = $entry;
        }

        return [
            'runId' => $run->id,
            'regionSlug' => $run->region_slug,
            'status' => $run->status,
            'day' => (int) $run->day,
            'movementLeft' => (int) $run->movement_left,
            'movementMax' => (int) $run->movement_max,
            'hero' => ['x' => (int) $run->hero_x, 'y' => (int) $run->hero_y],
            'size' => ['width' => (int) $map['width'], 'height' => (int) $map['height']],
            'terrain' => $map['terrain'],
            'revealed' => array_values($run->revealed ?? []),
            'objects' => $objects,
            'activeMerchant' => $run->active_merchant,
        ];
    }

    private function campaignTeam(User $user): Team
    {
        $team = $user->teams()
            ->where('type', 'campaign')
            ->with(['members.fighter.stats', 'members.fighter.skills', 'members.fighter.equipment.playerEquipment.definition'])
            ->first();

        if ($team === null || $team->members->isEmpty()) {
            throw new TeamNotReadyException;
        }

        return $team;
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
}
