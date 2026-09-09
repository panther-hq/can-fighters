<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'region_slug', 'times_cleared', 'first_cleared_at'])]
class PlayerRegionClear extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'times_cleared' => 'integer',
            'first_cleared_at' => 'datetime',
        ];
    }
}
