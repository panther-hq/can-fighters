<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'order', 'enemy_budget', 'enemy_pool', 'boss', 'drops'])]
class RegionDefinition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'enemy_budget' => 'integer',
            'enemy_pool' => 'array',
            'boss' => 'array',
            'drops' => 'array',
        ];
    }
}
