<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fighter_id', 'seed', 'input', 'before', 'after'])]
class FighterMutation extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'input' => 'array',
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
