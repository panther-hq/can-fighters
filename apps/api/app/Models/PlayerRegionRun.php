<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'region_slug', 'seed', 'map', 'current_row',
    'cleared_node_ids', 'last_node_id', 'active_merchant', 'status',
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
            'current_row' => 'integer',
            'cleared_node_ids' => 'array',
            'active_merchant' => 'array',
        ];
    }
}
