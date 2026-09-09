<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'seed', 'status', 'generation_version', 'input',
    'result_fighter_id', 'error', 'completed_at',
])]
class MixRequest extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'generation_version' => 'integer',
            'input' => 'array',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Fighter, $this>
     */
    public function resultFighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class, 'result_fighter_id');
    }
}
