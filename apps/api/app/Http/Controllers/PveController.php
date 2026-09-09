<?php

namespace App\Http\Controllers;

use App\Domain\PvE\FightStage;
use App\Models\Battle;
use App\Models\PveStageDefinition;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PveController extends Controller
{
    /**
     * Every stage with the player's progress + unlock state.
     */
    public function stages(Request $request): JsonResponse
    {
        $stages = PveStageDefinition::query()->orderBy('order')->get();
        $progress = $request->user()->stageProgress()->get()->keyBy('stage_slug');

        $data = $stages->map(function (PveStageDefinition $stage) use ($stages, $progress) {
            $previous = $stages->firstWhere('order', $stage->order - 1);
            $unlocked = $stage->order <= 1
                || ($previous !== null && (int) ($progress->get($previous->slug)->stars ?? 0) >= 1);

            return [
                'slug' => $stage->slug,
                'region' => $stage->region,
                'name' => $stage->name,
                'order' => $stage->order,
                'isBoss' => $stage->is_boss,
                'enemies' => $stage->enemies,
                'rewards' => [
                    'coins' => (int) $stage->rewards['coins'],
                    'xp' => (int) $stage->rewards['xp'],
                ],
                'stars' => (int) ($progress->get($stage->slug)->stars ?? 0),
                'cleared' => (int) ($progress->get($stage->slug)->stars ?? 0) >= 1,
                'unlocked' => $unlocked,
            ];
        });

        return response()->json(['stages' => $data]);
    }

    /**
     * Fight a stage. `Idempotency-Key` stops a retry double-rewarding.
     */
    public function battle(Request $request, PveStageDefinition $stage): JsonResponse
    {
        $user = $request->user();

        $outcome = Idempotency::run(
            $user,
            "pve.stage.{$stage->slug}",
            $request->header('Idempotency-Key'),
            fn (): array => [200, app(FightStage::class)->fight($user, $stage)],
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }

    /**
     * A stored battle for replay (spec §35).
     */
    public function battleShow(Request $request, Battle $battle): JsonResponse
    {
        abort_unless($battle->player_a_id === $request->user()->id, 404);

        return response()->json([
            'battleId' => $battle->id,
            'type' => $battle->type,
            'stageSlug' => $battle->stage_slug,
            'seed' => $battle->seed,
            'winner' => $battle->winner,
            'result' => $battle->result,
        ]);
    }
}
