<?php

namespace App\Http\Controllers;

use App\Domain\Arena\ChallengeOpponent;
use App\Domain\Arena\LeagueTable;
use App\Domain\Teams\SaveTeam;
use App\Http\Resources\TeamResource;
use App\Models\ArenaResult;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArenaController extends Controller
{
    public function __construct(private SaveTeam $saveTeam) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->playerProfile;
        $rating = (int) ($profile->rating ?? config('arena.starting_rating'));

        $defenseTeam = $user->teams()
            ->where('type', 'defense')
            ->with(['members.fighter.stats', 'members.fighter.skills'])
            ->first();

        return response()->json([
            'rating' => $rating,
            'league' => LeagueTable::forRating($rating),
            'rank' => $this->rankOf($rating),
            'defenseTeam' => $defenseTeam ? TeamResource::make($defenseTeam)->resolve() : null,
        ]);
    }

    public function setDefenseTeam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'members' => ['present', 'array', 'max:3'],
            'members.*.fighterId' => ['required', 'integer'],
            'members.*.position' => ['required', 'string'],
        ]);

        $members = array_map(
            fn (array $row) => ['fighterId' => (int) $row['fighterId'], 'position' => $row['position']],
            $validated['members'],
        );

        $team = ($this->saveTeam)($request->user(), 'defense', $members);

        return response()->json(['team' => TeamResource::make($team)->resolve()]);
    }

    public function opponents(Request $request): JsonResponse
    {
        $user = $request->user();
        $rating = (int) ($user->playerProfile->rating ?? config('arena.starting_rating'));
        $band = (int) config('arena.opponent_band');
        $count = (int) config('arena.opponent_count');

        $candidates = User::query()
            ->where('users.id', '!=', $user->id)
            ->whereHas('teams', fn ($q) => $q->where('type', 'defense'))
            ->with(['playerProfile', 'teams' => fn ($q) => $q->where('type', 'defense')->with('members.fighter.stats')])
            ->get()
            ->sortBy(fn (User $c) => abs(($c->playerProfile->rating ?? 1000) - $rating))
            ->take($count)
            ->map(function (User $c) {
                $r = (int) ($c->playerProfile->rating ?? config('arena.starting_rating'));
                $defense = $c->teams->firstWhere('type', 'defense');
                $power = $defense
                    ? $defense->members->sum(fn ($m) => (int) ($m->fighter->stats->power_score ?? 0))
                    : 0;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'rating' => $r,
                    'league' => LeagueTable::forRating($r),
                    'teamPower' => $power,
                ];
            })
            ->values();

        return response()->json(['opponents' => $candidates]);
    }

    public function challenge(Request $request, User $player, ChallengeOpponent $challenge): JsonResponse
    {
        $user = $request->user();

        $outcome = Idempotency::run(
            $user,
            "arena.challenge.{$player->id}",
            $request->header('Idempotency-Key'),
            fn (): array => [200, $challenge->challenge($user, $player)],
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }

    public function ranking(Request $request): JsonResponse
    {
        $top = PlayerProfile::query()
            ->with('user:id,name')
            ->orderByDesc('rating')
            ->limit(20)
            ->get()
            ->map(fn (PlayerProfile $p, int $i) => [
                'rank' => $i + 1,
                'name' => $p->user->name,
                'rating' => $p->rating,
                'league' => LeagueTable::forRating($p->rating),
            ]);

        $rating = (int) ($request->user()->playerProfile->rating ?? config('arena.starting_rating'));

        return response()->json([
            'top' => $top,
            'me' => [
                'rank' => $this->rankOf($rating),
                'rating' => $rating,
                'league' => LeagueTable::forRating($rating),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $rows = ArenaResult::query()
            ->with(['attacker:id,name', 'defender:id,name'])
            ->where(fn ($q) => $q->where('attacker_id', $userId)->orWhere('defender_id', $userId))
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (ArenaResult $r) use ($userId) {
                $asAttacker = $r->attacker_id === $userId;

                return [
                    'battleId' => $r->battle_id,
                    'role' => $asAttacker ? 'attack' : 'defense',
                    'opponent' => $asAttacker ? $r->defender->name : $r->attacker->name,
                    'won' => $asAttacker ? $r->attacker_won : ! $r->attacker_won,
                    'ratingDelta' => $asAttacker
                        ? $r->attacker_rating_after - $r->attacker_rating_before
                        : $r->defender_rating_after - $r->defender_rating_before,
                    'at' => $r->created_at?->toIso8601String(),
                ];
            });

        return response()->json(['history' => $rows]);
    }

    private function rankOf(int $rating): int
    {
        return DB::table('player_profiles')->where('rating', '>', $rating)->count() + 1;
    }
}
