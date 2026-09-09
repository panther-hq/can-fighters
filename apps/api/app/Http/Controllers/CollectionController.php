<?php

namespace App\Http\Controllers;

use App\Domain\Arena\LeagueTable;
use App\Models\IngredientDefinition;
use App\Models\PlayerDaily;
use App\Models\RegionDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A "collection log" (spec §4) — progress goals derived from existing data,
 * something to chase besides raw stats.
 */
class CollectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $rating = (int) ($user->playerProfile->rating ?? 1000);

        return response()->json([
            'ingredientsFound' => $user->ingredients()->where('quantity', '>', 0)->count(),
            'ingredientsTotal' => IngredientDefinition::count(),
            'fighters' => $user->fighters()->count(),
            'regionsCleared' => $user->regionClears()->where('times_cleared', '>=', 1)->count(),
            'regionsTotal' => RegionDefinition::count(),
            'bestDailyStreak' => (int) (PlayerDaily::where('user_id', $user->id)->value('best_streak') ?? 0),
            'arenaRating' => $rating,
            'league' => LeagueTable::forRating($rating),
        ]);
    }
}
