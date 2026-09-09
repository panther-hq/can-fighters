<?php

namespace App\Domain\Player;

use App\Models\CanDefinition;
use App\Models\User;

/**
 * What a brand-new player starts with (spec §67 — "receive starter cans",
 * §73 — "player receives 3 cans"). Called inside the register transaction.
 */
class GrantStarterPack
{
    public const CAN_SLUG = 'rusty';

    public const CAN_COUNT = 3;

    public function __invoke(User $user): void
    {
        $can = CanDefinition::firstWhere('slug', self::CAN_SLUG);

        if ($can !== null) {
            $user->cans()->create([
                'can_definition_id' => $can->id,
                'quantity' => self::CAN_COUNT,
            ]);
        }
    }
}
