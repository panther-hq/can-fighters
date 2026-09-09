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
            'stats' => $this->whenLoaded('stats', fn () => [
                'hp' => $this->stats->hp,
                'attack' => $this->stats->attack,
                'defense' => $this->stats->defense,
                'magic' => $this->stats->magic,
                'speed' => $this->stats->speed,
                'crit' => $this->stats->crit,
                'powerScore' => $this->stats->power_score,
                'budget' => $this->stats->budget,
                'pvpLegal' => $this->stats->pvp_legal,
            ]),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'slot' => $skill->slot,
                'skillFamily' => $skill->skill_family,
                'modifier' => $skill->modifier,
                'level' => $skill->level,
                'parameters' => $skill->parameters,
            ])),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment->map(fn ($worn) => [
                'slot' => $worn->slot,
                'playerEquipmentId' => $worn->player_equipment_id,
                'name' => $worn->playerEquipment->definition->name,
                'icon' => $worn->playerEquipment->definition->icon,
                'rarity' => $worn->playerEquipment->rarity,
                'rolledStats' => $worn->playerEquipment->rolled_stats,
            ])->values()),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
