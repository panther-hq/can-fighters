<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Everything the SPA needs to render the game after login / reconnect.
     *
     * Spec §43. Most sections are empty until their systems land in later
     * phases; the shape is stable so the frontend can rely on it now.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user()->load('playerProfile');
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
            'cans' => [],
            'notifications' => [],
            'serverTime' => now()->toIso8601String(),
        ]);
    }
}
