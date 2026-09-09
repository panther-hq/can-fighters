<?php

namespace App\Domain\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a team's roster (spec §20–§21). Rules: 1–3 members, one fighter per
 * slot, one slot per fighter, positions from {front, middle, back}, and every
 * fighter must belong to the player.
 */
class SaveTeam
{
    /**
     * @param  list<array{fighterId: int, position: string}>  $members
     */
    public function __invoke(User $user, string $type, array $members): Team
    {
        $this->validate($user, $members);

        return DB::transaction(function () use ($user, $type, $members): Team {
            $team = $user->teams()->firstOrCreate(['type' => $type]);
            $team->members()->delete();

            foreach ($members as $member) {
                $team->members()->create([
                    'fighter_id' => $member['fighterId'],
                    'position' => $member['position'],
                ]);
            }

            return $team->fresh(['members.fighter.stats', 'members.fighter.skills']);
        });
    }

    /**
     * @param  list<array{fighterId: int, position: string}>  $members
     */
    private function validate(User $user, array $members): void
    {
        if ($members === [] || count($members) > 3) {
            throw new InvalidTeamException('Drużyna musi mieć od 1 do 3 wojowników.');
        }

        $positions = array_column($members, 'position');
        if (count($positions) !== count(array_unique($positions))) {
            throw new InvalidTeamException('Każda pozycja może być zajęta tylko raz.');
        }
        foreach ($positions as $position) {
            if (! in_array($position, Team::POSITIONS, true)) {
                throw new InvalidTeamException("Nieprawidłowa pozycja: {$position}.");
            }
        }

        $fighterIds = array_column($members, 'fighterId');
        if (count($fighterIds) !== count(array_unique($fighterIds))) {
            throw new InvalidTeamException('Ten sam wojownik nie może stać na dwóch pozycjach.');
        }

        $owned = $user->fighters()->whereIn('id', $fighterIds)->count();
        if ($owned !== count(array_unique($fighterIds))) {
            throw new InvalidTeamException('Wybrany wojownik do Ciebie nie należy.');
        }
    }
}
