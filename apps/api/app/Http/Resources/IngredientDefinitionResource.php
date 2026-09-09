<?php

namespace App\Http\Resources;

use App\Models\IngredientDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IngredientDefinition
 */
class IngredientDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            'rarity' => $this->rarity,
            'powerValue' => $this->power_value,
            'tags' => $this->tags,
        ];
    }
}
