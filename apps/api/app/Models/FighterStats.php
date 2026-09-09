<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fighter_id', 'hp', 'attack', 'defense', 'magic', 'speed', 'crit', 'power_score'])]
class FighterStats extends Model
{
    protected $table = 'fighter_stats';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hp' => 'integer',
            'attack' => 'integer',
            'defense' => 'integer',
            'magic' => 'integer',
            'speed' => 'integer',
            'crit' => 'integer',
            'power_score' => 'integer',
        ];
    }
}
