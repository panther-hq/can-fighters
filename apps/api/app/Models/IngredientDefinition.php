<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'icon', 'rarity', 'power_value', 'tags'])]
class IngredientDefinition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'power_value' => 'integer',
            'tags' => 'array',
        ];
    }
}
