<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attacker_id', 'defender_id', 'battle_id', 'attacker_won',
    'attacker_rating_before', 'attacker_rating_after',
    'defender_rating_before', 'defender_rating_after',
])]
class ArenaResult extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attacker_won' => 'boolean',
            'attacker_rating_before' => 'integer',
            'attacker_rating_after' => 'integer',
            'defender_rating_before' => 'integer',
            'defender_rating_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function attacker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attacker_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function defender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'defender_id');
    }
}
