<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'icon', 'slot', 'rarity', 'base_stats', 'special'])]
class EquipmentDefinition extends Model
{
    public const SLOTS = ['weapon', 'armor', 'accessory'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['base_stats' => 'array'];
    }
}
