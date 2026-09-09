<?php

namespace Tests\Feature\PvE;

use App\Models\Team;
use App\Models\User;
use Tests\Concerns\MakesFighters;

trait PveConcerns
{
    use MakesFighters;

    /**
     * @param  array<string, mixed>  $fighterAttributes
     */
    protected function playerWithTeam(int $size = 3, array $fighterAttributes = []): User
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => 0, 'xp' => 0]);

        $team = $user->teams()->create(['type' => 'campaign']);
        $positions = Team::POSITIONS;

        for ($i = 0; $i < $size; $i++) {
            $fighter = $this->makeFighter($user, $fighterAttributes);
            $team->members()->create(['fighter_id' => $fighter->id, 'position' => $positions[$i]]);
        }

        return $user;
    }
}
