<?php

namespace App\Domain\Equipment;

use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use App\Models\PlayerEquipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Equip / unequip a piece and re-run the Balance Engine so the fighter's
 * stats reflect its gear (spec §23).
 */
class EquipFighter
{
    public function __construct(private StandardBalanceEngine $balance) {}

    public function equip(User $user, Fighter $fighter, int $playerEquipmentId): Fighter
    {
        return DB::transaction(function () use ($user, $fighter, $playerEquipmentId): Fighter {
            $piece = PlayerEquipment::query()
                ->where('user_id', $user->id)
                ->whereKey($playerEquipmentId)
                ->with('definition')
                ->lockForUpdate()
                ->first();

            if ($piece === null) {
                throw new EquipmentException('Nie masz tego przedmiotu.');
            }

            $slot = $piece->definition->slot;

            // Free the piece from whoever wears it, and clear this fighter's slot.
            $piece->fighterEquipment()->delete();
            $fighter->equipment()->where('slot', $slot)->delete();

            $fighter->equipment()->create([
                'player_equipment_id' => $piece->id,
                'slot' => $slot,
            ]);

            $this->balance->apply($fighter->fresh(['skills', 'equipment.playerEquipment']));

            return $fighter->fresh(['stats', 'skills', 'equipment.playerEquipment.definition']);
        });
    }

    public function unequip(User $user, Fighter $fighter, string $slot): Fighter
    {
        return DB::transaction(function () use ($fighter, $slot): Fighter {
            $fighter->equipment()->where('slot', $slot)->delete();
            $this->balance->apply($fighter->fresh(['skills', 'equipment.playerEquipment']));

            return $fighter->fresh(['stats', 'skills', 'equipment.playerEquipment.definition']);
        });
    }
}
