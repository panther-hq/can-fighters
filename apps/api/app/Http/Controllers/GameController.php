<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerCanResource;
use App\Http\Resources\PlayerProfileResource;
use App\Http\Resources\TeamResource;
use App\Models\PveStageDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Everything the SPA needs to render the game after login / reconnect.
     *
     * Spec §43. Sections fill in as their systems land; the shape is stable.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'playerProfile',
            'cans.definition',
            'teams' => fn ($q) => $q->where('type', 'campaign')->with('members.fighter.stats'),
        ]);
        $profile = $user->playerProfile;
        $team = $user->teams->firstWhere('type', 'campaign');

        $stagesTotal = PveStageDefinition::count();
        $stagesCleared = $user->stageProgress()->where('stars', '>=', 1)->count();

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
            'pve' => ['stagesCleared' => $stagesCleared, 'stagesTotal' => $stagesTotal],
            'notifications' => [],
            'serverTime' => now()->toIso8601String(),
        ]);
    }
}
