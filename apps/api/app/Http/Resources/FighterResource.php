<?php

namespace App\Http\Resources;

use App\Models\Fighter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fighter
 */
class FighterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'primaryClass' => $this->primary_class,
            'secondaryClass' => $this->secondary_class,
            'rarity' => $this->rarity,
            'personality' => $this->personality,
            'level' => $this->level,
            'xp' => $this->xp,
            'traits' => $this->traits,
            'visualDna' => $this->visual_dna,
            'suggestedSkills' => $this->suggested_skills,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
