<?php

namespace App\Http\Controllers;

use App\Models\PlayerEquipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    /**
     * The player's equipment and where each piece is worn.
     */
    public function index(Request $request): JsonResponse
    {
        $pieces = $request->user()->equipment()
            ->with(['definition', 'fighterEquipment.fighter'])
            ->get()
            ->map(fn (PlayerEquipment $piece) => [
                'id' => $piece->id,
                'slug' => $piece->definition->slug,
                'name' => $piece->definition->name,
                'icon' => $piece->definition->icon,
                'slot' => $piece->definition->slot,
                'rarity' => $piece->rarity,
                'special' => $piece->definition->special,
                'rolledStats' => $piece->rolled_stats,
                'equippedOnId' => $piece->fighterEquipment?->fighter_id,
                'equippedOnName' => $piece->fighterEquipment?->fighter?->name,
            ]);

        return response()->json(['equipment' => $pieces]);
    }
}
