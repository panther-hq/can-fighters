<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'streak', 'best_streak', 'last_claimed_on'])]
class PlayerDaily extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'streak' => 'integer',
            'best_streak' => 'integer',
            'last_claimed_on' => 'date',
        ];
    }
}
