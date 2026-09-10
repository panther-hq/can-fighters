<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'region_slug', 'seed', 'map', 'hero_x', 'hero_y',
    'movement_left', 'movement_max', 'day', 'revealed',
    'resolved_object_ids', 'active_merchant', 'status',
])]
class PlayerRegionRun extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_ABANDONED = 'abandoned';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'map' => 'array',
            'hero_x' => 'integer',
            'hero_y' => 'integer',
            'movement_left' => 'integer',
            'movement_max' => 'integer',
            'day' => 'integer',
            'revealed' => 'array',
            'resolved_object_ids' => 'array',
            'active_merchant' => 'array',
        ];
    }
}
