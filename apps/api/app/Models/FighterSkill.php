<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fighter_id', 'slot', 'skill_family', 'modifier', 'level', 'parameters'])]
class FighterSkill extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'level' => 'integer',
            'parameters' => 'array',
        ];
    }
}
