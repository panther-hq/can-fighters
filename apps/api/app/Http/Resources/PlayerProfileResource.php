<?php

namespace App\Http\Resources;

use App\Models\PlayerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlayerProfile
 */
class PlayerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'level' => $this->level,
            'xp' => $this->xp,
            'coins' => $this->coins,
            'rating' => $this->rating,
        ];
    }
}
