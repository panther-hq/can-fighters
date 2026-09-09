<?php

namespace App\Http\Controllers;

use App\Domain\Teams\SaveTeam;
use App\Http\Resources\TeamResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private SaveTeam $saveTeam) {}

    /**
     * The player's campaign team (spec §20). Returns `null` if not set up yet.
     */
    public function show(Request $request): JsonResponse
    {
        $team = $request->user()->teams()
            ->where('type', 'campaign')
            ->with(['members.fighter.stats', 'members.fighter.skills'])
            ->first();

        return response()->json([
            'team' => $team ? TeamResource::make($team)->resolve() : null,
        ]);
    }

    /**
     * Replace the campaign team's roster.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'members' => ['present', 'array', 'max:3'],
            'members.*.fighterId' => ['required', 'integer'],
            'members.*.position' => ['required', 'string'],
        ]);

        $members = array_map(
            fn (array $row) => ['fighterId' => (int) $row['fighterId'], 'position' => $row['position']],
            $validated['members'],
        );

        $team = ($this->saveTeam)($request->user(), 'campaign', $members);

        return response()->json(['team' => TeamResource::make($team)->resolve()]);
    }
}
