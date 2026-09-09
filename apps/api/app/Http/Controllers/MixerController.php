<?php

namespace App\Http\Controllers;

use App\Domain\Mixer\CharacterConcept;
use App\Domain\Mixer\MixerService;
use App\Http\Requests\MixIngredientsRequest;
use App\Http\Resources\MixRequestResource;
use App\Models\MixRequest;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MixerController extends Controller
{
    public function __construct(private MixerService $mixer) {}

    /**
     * Preview a concept without consuming ingredients (spec §55).
     */
    public function preview(MixIngredientsRequest $request): JsonResponse
    {
        $concept = $this->mixer->preview($request->user(), $request->ingredients());

        return response()->json(['concept' => $this->conceptToArray($concept)]);
    }

    /**
     * Consume ingredients and queue generation. Send an `Idempotency-Key`
     * header so a retry does not start a second mix (spec §58).
     */
    public function mix(MixIngredientsRequest $request): JsonResponse
    {
        $user = $request->user();

        $outcome = Idempotency::run(
            $user,
            'mixer.mix',
            $request->header('Idempotency-Key'),
            function () use ($user, $request): array {
                $mix = $this->mixer->requestMix($user, $request->ingredients());

                return [202, MixRequestResource::make($mix->fresh(['resultFighter']))->resolve()];
            },
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }

    public function show(Request $request, MixRequest $mix): JsonResponse
    {
        abort_unless($mix->user_id === $request->user()->id, 404);

        return response()->json(
            MixRequestResource::make($mix->load('resultFighter'))->resolve(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function conceptToArray(CharacterConcept $concept): array
    {
        return [
            'name' => $concept->name,
            'description' => $concept->description,
            'primaryClass' => $concept->primaryClass,
            'secondaryClass' => $concept->secondaryClass,
            'rarity' => $concept->rarity,
            'traits' => $concept->traits,
            'personality' => $concept->personality,
            'visualDna' => $concept->visualDna,
            'suggestedSkills' => $concept->suggestedSkills,
        ];
    }
}
