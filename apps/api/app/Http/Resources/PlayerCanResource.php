<?php

namespace App\Http\Resources;

use App\Models\PlayerCan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlayerCan
 */
class PlayerCanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->definition->slug,
            'name' => $this->definition->name,
            'icon' => $this->definition->icon,
            'rarity' => $this->definition->rarity,
            'quantity' => $this->quantity,
        ];
    }
}
