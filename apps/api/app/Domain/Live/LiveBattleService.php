<?php

namespace App\Domain\Live;

use App\Domain\Arena\Elo;
use App\Events\LiveBattleUpdated;
use App\Events\LiveMatchFound;
use App\Models\LiveBattle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Live PvP (spec §31, §45): matchmaking, a persisted battle room, and
 * server-authoritative round resolution. Each round both players pick one of
 * their alive fighters + an action; the second submission (or a timeout poll)
 * resolves the round through LiveRoundResolver.
 */
class LiveBattleService
{
    public function __construct(
        private Matchmaking $matchmaking,
        private LiveRoundResolver $resolver,
    ) {}

    /**
     * @return array{status: string, battleId?: int}
     */
    public function joinQueue(User $user): array
    {
        $this->campaignTeam($user); // guards: must have a team

        if ($this->matchmaking->isQueued($user->id)) {
            return ['status' => 'queued'];
        }

        $opponentId = $this->matchmaking->pair($user->id);
        if ($opponentId === null) {
            return ['status' => 'queued'];
        }

        $opponent = User::find($opponentId);
        if ($opponent === null || $opponent->teams()->where('type', 'campaign')->doesntExist()) {
            $this->matchmaking->pair($user->id); // requeue ourselves

            return ['status' => 'queued'];
        }

        // The player who was already waiting takes team A.
        return ['status' => 'matched', 'battleId' => $this->createBattle($opponent, $user)->id];
    }

    public function leaveQueue(User $user): void
    {
        $this->matchmaking->leave($user->id);
    }

    private function createBattle(User $a, User $b): LiveBattle
    {
        return DB::transaction(function () use ($a, $b): LiveBattle {
            $units = [
                ...$this->buildUnits($this->campaignTeam($a), 'A'),
                ...$this->buildUnits($this->campaignTeam($b), 'B'),
            ];

            $battle = LiveBattle::create([
                'player_a_id' => $a->id,
                'player_b_id' => $b->id,
                'seed' => random_int(1, PHP_INT_MAX),
                'status' => LiveBattle::STATUS_ACTIVE,
                'round' => 1,
                'state' => ['units' => $units, 'round' => 1],
                'pending' => [],
                'round_opened_at' => now(),
            ]);

            $this->announce(new LiveMatchFound($a->id, $battle->id));
            $this->announce(new LiveMatchFound($b->id, $battle->id));

            return $battle;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildUnits(Team $team, string $side): array
    {
        return $team->members->map(function ($member) use ($side) {
            $fighter = $member->fighter;
            $stats = $fighter->stats;

            return [
                'id' => $fighter->id,
                'name' => $fighter->name,
                'team' => $side,
                'position' => $member->position,
                'class' => $fighter->primary_class,
                'maxHp' => (int) ($stats->hp ?? 1),
                'hp' => (int) ($stats->hp ?? 1),
                'energy' => (int) config('live.energy_start'),
                'atk' => (int) ($stats->attack ?? 1),
                'def' => (int) ($stats->defense ?? 1),
                'mag' => (int) ($stats->magic ?? 1),
                'spd' => (int) ($stats->speed ?? 1),
                'crit' => (int) ($stats->crit ?? 0),
                'shield' => 0,
                'stunUntilRound' => 0,
                'cooldowns' => (object) [],
                'effects' => [],
                'skills' => $fighter->skills->map(fn ($s) => [
                    'slot' => (int) $s->slot,
                    'family' => $s->skill_family,
                    'params' => array_map('intval', $s->parameters ?? []),
                ])->values()->all(),
            ];
        })->all();
    }

    /**
     * @param  array{actorId: int, type: string, slot?: int, targetId?: int}  $action
     * @return array<string, mixed>
     */
    public function submitAction(User $user, LiveBattle $battle, array $action): array
    {
        if ($battle->status !== LiveBattle::STATUS_ACTIVE) {
            throw new LiveException('Walka jest już zakończona.');
        }

        $side = $battle->teamFor($user->id);
        if ($side === null) {
            throw new LiveException('To nie jest Twoja walka.', 404);
        }

        $this->validateAction($battle, $side, $action);

        $pending = $battle->pending;
        $pending[(string) $user->id] = $action;
        $battle->update(['pending' => $pending]);

        if (count($pending) >= 2) {
            return $this->resolveRound($battle->fresh());
        }

        return ['status' => 'waiting', 'battle' => $this->view($battle->fresh(), $user)];
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function validateAction(LiveBattle $battle, string $side, array $action): void
    {
        $units = collect($battle->state['units']);
        $actor = $units->first(fn ($u) => $u['id'] === ($action['actorId'] ?? null) && $u['team'] === $side && $u['hp'] > 0);
        if ($actor === null) {
            throw new LiveException('Wybierz swojego żywego wojownika.');
        }

        if (($action['type'] ?? '') === 'skill') {
            $slot = (int) ($action['slot'] ?? -1);
            $skill = collect($actor['skills'])->firstWhere('slot', $slot);
            if ($skill === null) {
                throw new LiveException('Nie ma takiej umiejętności.');
            }
            $cooldowns = (array) $actor['cooldowns'];
            if ($battle->round < (int) ($cooldowns[(string) $slot] ?? 0)) {
                throw new LiveException('Umiejętność się jeszcze ładuje.');
            }
            if ((int) $actor['energy'] < LiveEnergy::cost($skill['params'])) {
                throw new LiveException('Za mało energii.');
            }
        } elseif (($action['type'] ?? '') !== 'attack') {
            throw new LiveException('Nieznana akcja.');
        }

        if (isset($action['targetId']) && ! $units->contains(fn ($u) => $u['id'] === $action['targetId'])) {
            throw new LiveException('Nieprawidłowy cel.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveRound(LiveBattle $battle): array
    {
        return DB::transaction(function () use ($battle): array {
            /** @var LiveBattle $locked */
            $locked = LiveBattle::query()->whereKey($battle->id)->lockForUpdate()->first();
            if ($locked->status !== LiveBattle::STATUS_ACTIVE) {
                return ['status' => 'finished', 'events' => [], 'battle' => $this->view($locked, null)];
            }

            $out = $this->resolver->resolve(
                $locked->state,
                array_values($locked->pending),
                $locked->round,
                $locked->seed,
            );

            $locked->state = $out['state'];
            $locked->round = $out['state']['round'];
            $locked->pending = [];
            $locked->round_opened_at = now();

            if ($out['winner'] !== null) {
                $locked->status = LiveBattle::STATUS_FINISHED;
                $locked->winner = $out['winner'];
                $this->applyRating($locked);
            }
            $locked->save();

            $this->announce(new LiveBattleUpdated(
                $locked->id,
                $locked->round,
                $locked->status,
                $locked->winner,
                $out['events'],
            ));

            return [
                'status' => $locked->status === LiveBattle::STATUS_FINISHED ? 'finished' : 'resolved',
                'events' => $out['events'],
                'battle' => $this->view($locked, null),
            ];
        });
    }

    /**
     * Poll endpoint: if the round has been open past the timeout, auto-fill
     * missing actions with a basic attack and resolve.
     *
     * @return array<string, mixed>
     */
    public function pollTimeout(User $user, LiveBattle $battle): array
    {
        if ($battle->status !== LiveBattle::STATUS_ACTIVE) {
            return ['status' => 'finished', 'battle' => $this->view($battle, $user)];
        }

        $openFor = $battle->round_opened_at?->diffInSeconds(now()) ?? 0;
        if ($openFor < (int) config('live.round_timeout_seconds')) {
            return ['status' => 'waiting', 'battle' => $this->view($battle, $user)];
        }

        $pending = $battle->pending;
        foreach ([$battle->player_a_id => 'A', $battle->player_b_id => 'B'] as $playerId => $side) {
            if (isset($pending[(string) $playerId])) {
                continue;
            }
            $units = collect($battle->state['units']);
            $actor = $units->first(fn ($u) => $u['team'] === $side && $u['hp'] > 0);
            $enemy = $units->first(fn ($u) => $u['team'] !== $side && $u['hp'] > 0);
            if ($actor !== null) {
                $pending[(string) $playerId] = [
                    'actorId' => $actor['id'],
                    'type' => 'attack',
                    'targetId' => $enemy['id'] ?? null,
                ];
            }
        }
        $battle->update(['pending' => $pending]);

        return $this->resolveRound($battle->fresh());
    }

    private function applyRating(LiveBattle $battle): void
    {
        $a = $battle->player_a_id;
        $b = $battle->player_b_id;
        $profileA = User::find($a)?->playerProfile()->lockForUpdate()->first();
        $profileB = User::find($b)?->playerProfile()->lockForUpdate()->first();
        if ($profileA === null || $profileB === null) {
            return;
        }

        $updated = Elo::resolve($profileA->rating, $profileB->rating, $battle->winner === 'A');
        $profileA->update(['rating' => $updated['attacker']]);
        $profileB->update(['rating' => $updated['defender']]);
    }

    /**
     * @return array<string, mixed>
     */
    public function view(LiveBattle $battle, ?User $user): array
    {
        $submitted = array_map('intval', array_keys($battle->pending));

        return [
            'battleId' => $battle->id,
            'round' => $battle->round,
            'status' => $battle->status,
            'winner' => $battle->winner,
            'yourTeam' => $user ? $battle->teamFor($user->id) : null,
            'waitingOn' => array_values(array_diff(
                [$battle->player_a_id, $battle->player_b_id],
                $submitted,
            )),
            'units' => $battle->state['units'],
        ];
    }

    private function campaignTeam(User $user): Team
    {
        $team = $user->teams()
            ->where('type', 'campaign')
            ->with(['members.fighter.stats', 'members.fighter.skills'])
            ->first();

        if ($team === null || $team->members->isEmpty()) {
            throw new LiveException('Najpierw ustaw drużynę.');
        }

        return $team;
    }

    private function announce(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
