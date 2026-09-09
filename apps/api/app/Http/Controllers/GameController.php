<?php

namespace App\Http\Controllers;

use App\Domain\Arena\LeagueTable;
use App\Domain\Economy\DailyReward;
use App\Http\Resources\PlayerCanResource;
use App\Http\Resources\PlayerProfileResource;
use App\Http\Resources\TeamResource;
use App\Models\PlayerRegionRun;
use App\Models\RegionDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Everything the SPA needs to render the game after login / reconnect.
     *
     * Spec §43. Sections fill in as their systems land; the shape is stable.
     */
    public function bootstrap(Request $request, DailyReward $daily): JsonResponse
    {
        $user = $request->user()->load([
            'playerProfile',
            'cans.definition',
            'teams.members.fighter.stats',
        ]);
        $profile = $user->playerProfile;
        $team = $user->teams->firstWhere('type', 'campaign');
        $defense = $user->teams->firstWhere('type', 'defense');

        $rating = (int) ($profile->rating ?? 1000);
        $dailyStatus = $daily->status($user);

        return response()->json([
            'player' => [
                'id' => $user->id,
                'displayName' => $user->name,
                'profile' => PlayerProfileResource::make($profile),
            ],
            'currencies' => [
                'coins' => $profile->coins,
            ],
            'team' => $team ? TeamResource::make($team)->resolve() : null,
            'cans' => PlayerCanResource::collection($user->cans),
            'pve' => [
                'regionsCleared' => $user->regionClears()->where('times_cleared', '>=', 1)->count(),
                'regionsTotal' => RegionDefinition::count(),
                'onExpedition' => PlayerRegionRun::where('user_id', $user->id)
                    ->where('status', 'active')->exists(),
            ],
            'arena' => [
                'rating' => $rating,
                'league' => LeagueTable::forRating($rating),
                'defenseSet' => $defense !== null && $defense->members->isNotEmpty(),
            ],
            'daily' => [
                'canClaim' => $dailyStatus['canClaim'],
                'streak' => $dailyStatus['streak'],
            ],
            'notifications' => [],
            'serverTime' => now()->toIso8601String(),
        ]);
    }
}
