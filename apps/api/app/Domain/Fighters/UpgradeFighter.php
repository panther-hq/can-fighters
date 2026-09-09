<?php

namespace App\Domain\Fighters;

use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpgradeFighter
{
    public const COST_PER_LEVEL = 100;

    public function __construct(private StandardBalanceEngine $balance) {}

    public function __invoke(User $user, Fighter $fighter): Fighter
    {
        return DB::transaction(function () use ($user, $fighter): Fighter {
            $cost = $fighter->level * self::COST_PER_LEVEL;

            $profile = $user->playerProfile()->lockForUpdate()->first();
            if ($profile->coins < $cost) {
                throw new NotEnoughCoinsException;
            }

            $profile->decrement('coins', $cost);
            $fighter->increment('level');
            $this->balance->apply($fighter->refresh());

            return $fighter->fresh(['stats', 'skills']);
        });
    }
}
