<?php

namespace Tests\Concerns;

use App\Domain\Balance\StandardBalanceEngine;
use App\Models\Fighter;
use App\Models\User;

trait MakesFighters
{
    /**
     * A fully-formed fighter: 2 skills + balanced stats.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeFighter(User $user, array $attributes = []): Fighter
    {
        $fighter = Fighter::factory()->for($user)->create($attributes);

        $fighter->skills()->create(['slot' => 0, 'skill_family' => 'direct_damage', 'modifier' => null, 'parameters' => []]);
        $fighter->skills()->create(['slot' => 1, 'skill_family' => 'shield', 'modifier' => null, 'parameters' => []]);

        app(StandardBalanceEngine::class)->apply($fighter->load('skills'));

        return $fighter->fresh(['stats', 'skills']);
    }
}
