<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'region', 'name', 'order', 'is_boss', 'enemy_budget', 'enemies', 'rewards'])]
class PveStageDefinition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_boss' => 'boolean',
            'enemy_budget' => 'integer',
            'enemies' => 'array',
            'rewards' => 'array',
        ];
    }
}
