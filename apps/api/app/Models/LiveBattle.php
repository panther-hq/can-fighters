<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'player_a_id', 'player_b_id', 'seed', 'status', 'round', 'winner',
    'state', 'pending', 'round_opened_at',
])]
class LiveBattle extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'round' => 'integer',
            'state' => 'array',
            'pending' => 'array',
            'round_opened_at' => 'datetime',
        ];
    }

    public function teamFor(int $userId): ?string
    {
        return match ($userId) {
            $this->player_a_id => 'A',
            $this->player_b_id => 'B',
            default => null,
        };
    }
}
