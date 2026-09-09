<?php

namespace App\Domain\Arena;

use App\Domain\Battle\BattleEngine;
use App\Domain\Battle\FighterCombatants;
use App\Domain\Battle\ValueObjects\CombatantInput;
use App\Events\ArenaDefenseAttacked;
use App\Events\ArenaRatingUpdated;
use App\Models\ArenaResult;
use App\Models\Battle;
use App\Models\Fighter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Asynchronous PvP (spec §29): attacker's campaign team vs the defender's
 * standing defense team. Immutable snapshots, backend-run battle, Elo rating
 * update, notify the defender. The defender need not be online.
 */
class ChallengeOpponent
{
    public function __construct(
        private BattleEngine $engine,
        private FighterCombatants $fighterCombatants,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function challenge(User $attacker, User $defender): array
    {
        if ($attacker->id === $defender->id) {
            throw new ArenaException('Nie możesz wyzwać samego siebie.');
        }

        return DB::transaction(function () use ($attacker, $defender): array {
            $attackTeam = $this->requireTeam($attacker, 'campaign', 'Najpierw ustaw drużynę.');
            $defenseTeam = $this->requireTeam($defender, 'defense', 'Ten gracz nie ma drużyny obronnej.');

            $teamA = $this->fighterCombatants->fromEntries($this->entries($attackTeam), 'A');
            $teamB = $this->fighterCombatants->fromEntries($this->entries($defenseTeam), 'B');

            $seed = random_int(1, PHP_INT_MAX);
            $result = $this->engine->run([...$teamA, ...$teamB], $seed);
            $won = $result->winner === 'A';

            $battle = Battle::create([
                'type' => 'arena',
                'seed' => $seed,
                'battle_version' => $result->version,
                'player_a_id' => $attacker->id,
                'player_b_id' => $defender->id,
                'winner' => $result->winner,
                'result' => $result->jsonSerialize(),
            ]);
            $battle->snapshots()->create(['team' => 'A', 'combatants' => $this->snapshot($teamA)]);
            $battle->snapshots()->create(['team' => 'B', 'combatants' => $this->snapshot($teamB)]);

            $attackerProfile = $attacker->playerProfile()->lockForUpdate()->first();
            $defenderProfile = $defender->playerProfile()->lockForUpdate()->first();
            $ratingBeforeA = $attackerProfile->rating;
            $ratingBeforeD = $defenderProfile->rating;

            $updated = Elo::resolve($ratingBeforeA, $ratingBeforeD, $won);
            $attackerProfile->update(['rating' => $updated['attacker']]);
            $defenderProfile->update(['rating' => $updated['defender']]);

            ArenaResult::create([
                'attacker_id' => $attacker->id,
                'defender_id' => $defender->id,
                'battle_id' => $battle->id,
                'attacker_won' => $won,
                'attacker_rating_before' => $ratingBeforeA,
                'attacker_rating_after' => $updated['attacker'],
                'defender_rating_before' => $ratingBeforeD,
                'defender_rating_after' => $updated['defender'],
            ]);

            $this->announce(new ArenaDefenseAttacked($defender->id, $attacker->name, ! $won));
            $this->announce(new ArenaRatingUpdated($attacker->id, $updated['attacker']));
            $this->announce(new ArenaRatingUpdated($defender->id, $updated['defender']));

            return [
                'battleId' => $battle->id,
                'won' => $won,
                'result' => $result->jsonSerialize(),
                'rating' => [
                    'before' => $ratingBeforeA,
                    'after' => $updated['attacker'],
                    'delta' => $updated['attacker'] - $ratingBeforeA,
                ],
                'defender' => [
                    'id' => $defender->id,
                    'name' => $defender->name,
                    'rating' => $updated['defender'],
                    'league' => LeagueTable::forRating($updated['defender']),
                ],
            ];
        });
    }

    private function requireTeam(User $user, string $type, string $message): Team
    {
        $team = $user->teams()
            ->where('type', $type)
            ->with(['members.fighter.stats', 'members.fighter.skills', 'members.fighter.equipment.playerEquipment.definition'])
            ->first();

        if ($team === null || $team->members->isEmpty()) {
            throw new ArenaException($message);
        }

        return $team;
    }

    /**
     * @return Collection<int, array{fighter: Fighter, position: string}>
     */
    private function entries(Team $team)
    {
        return $team->members->map(fn ($m) => ['fighter' => $m->fighter, 'position' => $m->position]);
    }

    /**
     * @param  list<CombatantInput>  $combatants
     * @return list<array<string, mixed>>
     */
    private function snapshot(array $combatants): array
    {
        return array_map(fn (CombatantInput $c) => (array) $c, $combatants);
    }

    private function announce(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
