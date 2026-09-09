<?php

namespace App\Http\Controllers;

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
    public function index(Request $request): AnonymousResourceCollection
    {
        return FighterResource::collection(
            $request->user()->fighters()->with(['stats', 'skills'])->latest()->get(),
        );
    }

    public function show(Request $request, Fighter $fighter): FighterResource
    {
        abort_unless($fighter->user_id === $request->user()->id, 404);

        return FighterResource::make($fighter->load(['stats', 'skills']));
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
