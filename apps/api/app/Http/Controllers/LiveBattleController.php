<?php

namespace App\Http\Controllers;

use App\Domain\Live\LiveBattleService;
use App\Models\LiveBattle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LiveBattleController extends Controller
{
    public function __construct(private LiveBattleService $live) {}

    public function queue(Request $request): JsonResponse
    {
        return response()->json($this->live->joinQueue($request->user()));
    }

    public function leaveQueue(Request $request): Response
    {
        $this->live->leaveQueue($request->user());

        return response()->noContent();
    }

    public function show(Request $request, LiveBattle $liveBattle): JsonResponse
    {
        abort_unless($liveBattle->teamFor($request->user()->id) !== null, 404);

        return response()->json(['battle' => $this->live->view($liveBattle, $request->user())]);
    }

    public function act(Request $request, LiveBattle $liveBattle): JsonResponse
    {
        $data = $request->validate([
            'actorId' => ['required', 'integer'],
            'type' => ['required', 'in:attack,skill'],
            'slot' => ['nullable', 'integer', 'in:0,1'],
            'targetId' => ['nullable', 'integer'],
        ]);

        return response()->json($this->live->submitAction($request->user(), $liveBattle, [
            'actorId' => (int) $data['actorId'],
            'type' => $data['type'],
            'slot' => isset($data['slot']) ? (int) $data['slot'] : null,
            'targetId' => isset($data['targetId']) ? (int) $data['targetId'] : null,
        ]));
    }

    public function resolve(Request $request, LiveBattle $liveBattle): JsonResponse
    {
        abort_unless($liveBattle->teamFor($request->user()->id) !== null, 404);

        return response()->json($this->live->pollTimeout($request->user(), $liveBattle));
    }
}
