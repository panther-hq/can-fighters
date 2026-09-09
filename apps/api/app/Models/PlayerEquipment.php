<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'equipment_definition_id', 'rarity', 'seed', 'rolled_stats'])]
class PlayerEquipment extends Model
{
    protected $table = 'player_equipment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['seed' => 'integer', 'rolled_stats' => 'array'];
    }

    /**
     * @return BelongsTo<EquipmentDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(EquipmentDefinition::class, 'equipment_definition_id');
    }

    /**
     * @return HasOne<FighterEquipment, $this>
     */
    public function fighterEquipment(): HasOne
    {
        return $this->hasOne(FighterEquipment::class);
    }
}
