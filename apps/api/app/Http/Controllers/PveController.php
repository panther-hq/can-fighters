<?php

namespace App\Http\Controllers;

use App\Domain\PvE\RegionRun;
use App\Models\Battle;
use App\Models\PlayerRegionRun;
use App\Models\User;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PveController extends Controller
{
    public function __construct(private RegionRun $regionRun) {}

    public function regions(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $this->regionRun->activeRun($user);

        return response()->json([
            'regions' => $this->regionRun->regions($user),
            'run' => $active ? $this->regionRun->view($active) : null,
        ]);
    }

    public function startRun(Request $request, string $region): JsonResponse
    {
        $run = $this->regionRun->start($request->user(), $region);

        return response()->json(['run' => $this->regionRun->view($run)]);
    }

    public function run(Request $request): JsonResponse
    {
        $active = $this->regionRun->activeRun($request->user());

        return response()->json(['run' => $active ? $this->regionRun->view($active) : null]);
    }

    public function move(Request $request): JsonResponse
    {
        $user = $request->user();
        $run = $this->activeRunOr404($user);
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
        ]);

        $outcome = Idempotency::run(
            $user,
            "pve.run.{$run->id}.move",
            $request->header('Idempotency-Key'),
            fn (): array => [200, $this->regionRun->move($user, $run, (int) $data['x'], (int) $data['y'])],
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }

    public function endDay(Request $request): JsonResponse
    {
        $user = $request->user();
        $run = $this->activeRunOr404($user);

        return response()->json($this->regionRun->endDay($user, $run));
    }

    public function buy(Request $request): JsonResponse
    {
        $user = $request->user();
        $run = $this->activeRunOr404($user);
        $offer = $request->validate(['offerId' => ['required', 'string']])['offerId'];

        return response()->json($this->regionRun->buyFromMerchant($user, $run, $offer));
    }

    public function leaveMerchant(Request $request): JsonResponse
    {
        $run = $this->activeRunOr404($request->user());

        return response()->json($this->regionRun->leaveMerchant($run));
    }

    public function abandon(Request $request): JsonResponse
    {
        $run = $this->regionRun->activeRun($request->user());
        if ($run !== null) {
            $this->regionRun->abandon($run);
        }

        return response()->json(['run' => null]);
    }

    public function battleShow(Request $request, Battle $battle): JsonResponse
    {
        abort_unless($battle->player_a_id === $request->user()->id, 404);

        return response()->json([
            'battleId' => $battle->id,
            'seed' => $battle->seed,
            'winner' => $battle->winner,
            'result' => $battle->result,
        ]);
    }

    private function activeRunOr404(User $user): PlayerRegionRun
    {
        $run = $this->regionRun->activeRun($user);
        abort_if($run === null, 404, 'Brak aktywnej wyprawy.');

        return $run;
    }
}
