<?php

namespace App\Http\Resources;

use App\Models\PlayerIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlayerIngredient
 */
class PlayerIngredientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->definition->slug,
            'name' => $this->definition->name,
            'icon' => $this->definition->icon,
            'rarity' => $this->definition->rarity,
            'tags' => $this->definition->tags,
            'quantity' => $this->quantity,
        ];
    }
}
