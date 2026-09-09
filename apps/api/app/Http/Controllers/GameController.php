<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerCanResource;
use App\Http\Resources\PlayerProfileResource;
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
        $user = $request->user()->load(['playerProfile', 'cans.definition']);
        $profile = $user->playerProfile;

        return response()->json([
            'player' => [
                'id' => $user->id,
                'displayName' => $user->name,
                'profile' => PlayerProfileResource::make($profile),
            ],
            'currencies' => [
                'coins' => $profile->coins,
            ],
            'team' => null,
            'cans' => PlayerCanResource::collection($user->cans),
            'notifications' => [],
            'serverTime' => now()->toIso8601String(),
        ]);
    }
}
