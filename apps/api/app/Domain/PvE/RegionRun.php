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
 * Drives a roguelike region expedition: a branching node map you route through
 * from the bottom to the boss. Each node clears on visit; a lost battle or a
 * sprung ambush ends the run.
 */
class RegionRun
{
    public function __construct(
        private RegionMapGenerator $generator,
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

            return PlayerRegionRun::create([
                'user_id' => $user->id,
                'region_slug' => $region->slug,
                'seed' => $seed,
                'map' => $this->generator->generate($region, $seed),
                'current_row' => -1,
                'cleared_node_ids' => [],
                'status' => PlayerRegionRun::STATUS_ACTIVE,
            ]);
        });
    }

    public function abandon(PlayerRegionRun $run): void
    {
        if ($run->status === PlayerRegionRun::STATUS_ACTIVE) {
            $run->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);
        }
    }

    /**
     * @return list<string>
     */
    public function reachableNodeIds(PlayerRegionRun $run): array
    {
        if ($run->status !== PlayerRegionRun::STATUS_ACTIVE || $run->active_merchant !== null) {
            return [];
        }
        if ($run->current_row === -1) {
            return array_column($run->map['rows'][0]['nodes'], 'id');
        }
        $last = $this->findNode($run, $run->last_node_id);

        return $last['edges'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function visit(User $user, PlayerRegionRun $run, string $nodeId): array
    {
        if ($run->status !== PlayerRegionRun::STATUS_ACTIVE) {
            throw new StageLockedException('Ta wyprawa się zakończyła.');
        }
        if ($run->active_merchant !== null) {
            throw new StageLockedException('Najpierw opuść kupca.');
        }
        if (! in_array($nodeId, $this->reachableNodeIds($run), true)) {
            throw new StageLockedException('Tam nie możesz teraz przejść.');
        }
        if (in_array($nodeId, $run->cleared_node_ids, true)) {
            throw new StageLockedException('Ten węzeł jest już przebyty.');
        }

        $node = $this->findNode($run, $nodeId);
        $region = RegionDefinition::query()->where('slug', $run->region_slug)->firstOrFail();

        return match ($node['type']) {
            'battle', 'elite', 'boss' => $this->resolveBattle($user, $run, $node, $region),
            'loot' => $this->resolveLoot($user, $run, $node, $region),
            'event' => $this->resolveEvent($user, $run, $node, $region),
            'merchant' => $this->openMerchant($run, $node, $region),
            default => throw new StageLockedException('Nieznany węzeł.'),
        };
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
        if ($run->active_merchant === null) {
            return ['run' => $this->view($run)];
        }
        $node = $this->findNode($run, $run->active_merchant['nodeId']);
        $this->advance($run, $node);
        $run->update(['active_merchant' => null]);

        return ['run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolveBattle(User $user, PlayerRegionRun $run, array $node, RegionDefinition $region): array
    {
        $team = $this->campaignTeam($user);
        $outcome = $this->runBattle->fight($user, $team, $node, $region);

        if (! $outcome['won']) {
            $run->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);

            return [
                'type' => $node['type'],
                'won' => false,
                'runEnded' => true,
                'battleId' => $outcome['battle']->id,
                'result' => $outcome['result'],
                'run' => $this->view($run->fresh()),
            ];
        }

        $this->advance($run, $node);
        if ($node['type'] === 'boss') {
            $run->update(['status' => PlayerRegionRun::STATUS_CLEARED]);
            $this->recordClear($user, $region);
        }

        return [
            'type' => $node['type'],
            'won' => true,
            'battleId' => $outcome['battle']->id,
            'result' => $outcome['result'],
            'rewards' => $outcome['rewards'],
            'run' => $this->view($run->fresh()),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolveLoot(User $user, PlayerRegionRun $run, array $node, RegionDefinition $region): array
    {
        $rng = new SeededRng((int) $run->seed + crc32($node['id']));
        $reward = $rng->chance(32)
            ? $this->grant->grant($user, ['type' => 'can', 'slug' => $region->drops['cans'][0] ?? 'rusty', 'qty' => 1])
            : $this->grant->grant($user, ['type' => 'ingredient', 'slug' => $rng->pick($region->drops['ingredients']), 'qty' => 2 + $rng->int(2)]);

        $this->advance($run, $node);

        return ['type' => 'loot', 'rewards' => [$reward], 'run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolveEvent(User $user, PlayerRegionRun $run, array $node, RegionDefinition $region): array
    {
        $rng = new SeededRng((int) $run->seed + crc32('event'.$node['id']));
        $outcome = $this->weightedPick($rng, config('regions.event_weights'));

        if ($outcome === 'skarb') {
            $rewards = [$this->grant->grant($user, ['type' => 'coins', 'amount' => 60 + $rng->int(80)])];
            if ($rng->chance(25)) {
                $rewards[] = $this->grant->grant($user, ['type' => 'can', 'slug' => $region->drops['cans'][0] ?? 'rusty', 'qty' => 1]);
            }
            $this->advance($run, $node);

            return ['type' => 'event', 'event' => 'skarb', 'rewards' => $rewards, 'run' => $this->view($run->fresh())];
        }

        if ($outcome === 'trening') {
            $xp = 30 + (int) $node['row'] * 6;
            $this->runBattle->awardFighterXp($this->campaignTeam($user), $xp);
            $this->advance($run, $node);

            return ['type' => 'event', 'event' => 'trening', 'rewards' => [['type' => 'fighterXp', 'amount' => $xp]], 'run' => $this->view($run->fresh())];
        }

        if ($outcome === 'handlarz') {
            return $this->openMerchant($run, $node, $region, rare: true);
        }

        // pulapka
        if ($rng->chance(50)) {
            $lost = 40 + $rng->int(50);
            $user->playerProfile()->decrement('coins', min($lost, (int) $user->playerProfile->coins));
            $this->advance($run, $node);

            return ['type' => 'event', 'event' => 'pulapka', 'rewards' => [['type' => 'coins', 'amount' => -$lost]], 'run' => $this->view($run->fresh())];
        }

        $team = $this->campaignTeam($user);
        $ambush = [
            'id' => $node['id'],
            'row' => $node['row'],
            'type' => 'battle',
            'budget' => (int) round(($region->enemy_budget + $node['row'] * (int) config('regions.budget_step_per_row')) * 0.8),
            'enemies' => array_slice($region->enemy_pool, 0, 2),
        ];
        $outcome = $this->runBattle->fight($user, $team, $ambush, $region);
        if (! $outcome['won']) {
            $run->update(['status' => PlayerRegionRun::STATUS_ABANDONED]);

            return ['type' => 'event', 'event' => 'zasadzka', 'won' => false, 'runEnded' => true, 'battleId' => $outcome['battle']->id, 'result' => $outcome['result'], 'run' => $this->view($run->fresh())];
        }
        $this->advance($run, $node);

        return ['type' => 'event', 'event' => 'zasadzka', 'won' => true, 'battleId' => $outcome['battle']->id, 'result' => $outcome['result'], 'rewards' => $outcome['rewards'], 'run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function openMerchant(PlayerRegionRun $run, array $node, RegionDefinition $region, bool $rare = false): array
    {
        $rng = new SeededRng((int) $run->seed + crc32('shop'.$node['id']));
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

        $run->update(['active_merchant' => ['nodeId' => $node['id'], 'offers' => $offers]]);

        return ['type' => 'merchant', 'offers' => $offers, 'run' => $this->view($run->fresh())];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function advance(PlayerRegionRun $run, array $node): void
    {
        $cleared = $run->cleared_node_ids;
        $cleared[] = $node['id'];
        $run->update([
            'cleared_node_ids' => array_values(array_unique($cleared)),
            'current_row' => $node['row'],
            'last_node_id' => $node['id'],
        ]);
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
     * @return array<string, mixed>
     */
    private function findNode(PlayerRegionRun $run, ?string $nodeId): array
    {
        foreach ($run->map['rows'] as $row) {
            foreach ($row['nodes'] as $node) {
                if ($node['id'] === $nodeId) {
                    return $node;
                }
            }
        }

        throw new StageLockedException('Nie ma takiego węzła.');
    }

    /**
     * @return array<string, mixed>
     */
    public function view(PlayerRegionRun $run): array
    {
        return [
            'runId' => $run->id,
            'regionSlug' => $run->region_slug,
            'status' => $run->status,
            'currentRow' => $run->current_row,
            'clearedNodeIds' => $run->cleared_node_ids,
            'reachableNodeIds' => $this->reachableNodeIds($run),
            'activeMerchant' => $run->active_merchant,
            'map' => $run->map,
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
