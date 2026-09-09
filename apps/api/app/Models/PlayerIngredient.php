<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ingredient_definition_id', 'quantity'])]
class PlayerIngredient extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<IngredientDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(IngredientDefinition::class, 'ingredient_definition_id');
    }
}
