<?php

namespace App\Http\Controllers;

use App\Domain\Equipment\EquipFighter;
use App\Domain\Fighters\MutateFighter;
use App\Domain\Fighters\UpgradeFighter;
use App\Http\Requests\MixIngredientsRequest;
use App\Http\Resources\FighterResource;
use App\Models\Fighter;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FighterController extends Controller
{
    private const WITH = ['stats', 'skills', 'equipment.playerEquipment.definition'];

    public function index(Request $request): AnonymousResourceCollection
    {
        return FighterResource::collection(
            $request->user()->fighters()->with(self::WITH)->latest()->get(),
        );
    }

    public function show(Request $request, Fighter $fighter): FighterResource
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        return FighterResource::make($fighter->load(self::WITH));
    }

    public function equip(Request $request, Fighter $fighter, EquipFighter $equip): FighterResource
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        $data = $request->validate(['playerEquipmentId' => ['required', 'integer']]);

        return FighterResource::make($equip->equip($request->user(), $fighter, (int) $data['playerEquipmentId']));
    }

    public function unequip(Request $request, Fighter $fighter, EquipFighter $equip): FighterResource
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        $data = $request->validate(['slot' => ['required', 'in:weapon,armor,accessory']]);

        return FighterResource::make($equip->unequip($request->user(), $fighter, $data['slot']));
    }

    public function upgrade(Request $request, Fighter $fighter, UpgradeFighter $upgrade): FighterResource
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        return FighterResource::make($upgrade($request->user(), $fighter));
    }

    public function mutate(MixIngredientsRequest $request, Fighter $fighter, MutateFighter $mutate): JsonResponse
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        $user = $request->user();

        $outcome = Idempotency::run(
            $user,
            "fighter.mutate.{$fighter->id}",
            $request->header('Idempotency-Key'),
            fn (): array => [
                200,
                ['data' => FighterResource::make($mutate($user, $fighter, $request->ingredients()))->resolve()],
            ],
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }
}
