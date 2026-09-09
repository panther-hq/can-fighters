<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'seed', 'battle_version', 'player_a_id', 'player_b_id',
    'stage_slug', 'winner', 'result',
])]
class Battle extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'battle_version' => 'integer',
            'result' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<BattleSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(BattleSnapshot::class);
    }
}
