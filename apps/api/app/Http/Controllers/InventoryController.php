<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlayerCanResource;
use App\Http\Resources\PlayerIngredientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * The player's ingredients and cans (spec §55).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $ingredients = $user->ingredients()
            ->where('quantity', '>', 0)
            ->with('definition')
            ->get()
            ->sortBy('definition.id')
            ->values();

        $cans = $user->cans()->with('definition')->orderBy('id')->get();

        return response()->json([
            'ingredients' => PlayerIngredientResource::collection($ingredients),
            'cans' => PlayerCanResource::collection($cans),
        ]);
    }
}
