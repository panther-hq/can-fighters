<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'members' => $this->members
                ->sortBy(fn ($member) => array_search($member->position, Team::POSITIONS, true))
                ->values()
                ->map(fn ($member) => [
                    'position' => $member->position,
                    'fighter' => FighterResource::make($member->fighter),
                ]),
        ];
    }
}
