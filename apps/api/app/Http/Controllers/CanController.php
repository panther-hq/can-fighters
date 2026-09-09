<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\OpenCanService;
use App\Http\Resources\PlayerCanResource;
use App\Models\PlayerCan;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CanController extends Controller
{
    /**
     * The player's owned cans.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PlayerCanResource::collection(
            $request->user()->cans()->with('definition')->orderBy('id')->get(),
        );
    }

    /**
     * Open one can from the given stack. Send an `Idempotency-Key` header so a
     * retried request does not open a second can (spec §58).
     */
    public function open(Request $request, PlayerCan $can): JsonResponse
    {
        $user = $request->user();
        abort_unless($can->user_id === $user->id, 404);

        $outcome = Idempotency::run(
            $user,
            'cans.open',
            $request->header('Idempotency-Key'),
            function () use ($user, $can): array {
                $result = app(OpenCanService::class)->open($user, $can);

                return [201, [
                    'openingId' => $result->openingId,
                    'seed' => $result->seed,
                    'received' => $result->received,
                ]];
            },
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }
}
