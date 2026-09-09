<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'stage_slug', 'stars', 'best_battle_id', 'cleared_at'])]
class PlayerStageProgress extends Model
{
    protected $table = 'player_stage_progress';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'cleared_at' => 'datetime',
        ];
    }
}
