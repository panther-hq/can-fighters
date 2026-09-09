<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fighter_id', 'player_equipment_id', 'slot'])]
class FighterEquipment extends Model
{
    /**
     * @return BelongsTo<Fighter, $this>
     */
    public function fighter(): BelongsTo
    {
        return $this->belongsTo(Fighter::class);
    }

    /**
     * @return BelongsTo<PlayerEquipment, $this>
     */
    public function playerEquipment(): BelongsTo
    {
        return $this->belongsTo(PlayerEquipment::class);
    }
}
