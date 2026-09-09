<?php

namespace App\Domain\PvE;

use App\Domain\Balance\StandardBalanceEngine;
use App\Domain\Battle\BattleEngine;
use App\Domain\Battle\FighterCombatants;
use App\Domain\Battle\ValueObjects\CombatantInput;
use App\Domain\Economy\GrantReward;
use App\Models\Battle;
use App\Models\RegionDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\SeededRng;

/**
 * Fights one battle/elite/boss node: build teams, run the engine, persist the
 * battle + snapshots, and on a win grant the node's rewards + fighter XP.
 */
class RunBattle
{
    private const FIGHTER_XP_PER_LEVEL = 100;

    public function __construct(
        private BattleEngine $engine,
        private FighterCombatants $fighterCombatants,
        private EnemyCombatants $enemyCombatants,
        private GrantReward $grant,
        private StandardBalanceEngine $balance,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @return array{battle: Battle, won: bool, result: array<string, mixed>, rewards: list<array<string, mixed>>}
     */
    public function fight(User $user, Team $team, array $node, RegionDefinition $region): array
    {
        $entries = $team->members->map(fn ($m) => ['fighter' => $m->fighter, 'position' => $m->position]);
        $teamA = $this->fighterCombatants->fromEntries($entries, 'A');
        $teamB = $this->enemyCombatants->forSpec($node['enemies'], (int) $node['budget']);

        $seed = random_int(1, PHP_INT_MAX);
        $result = $this->engine->run([...$teamA, ...$teamB], $seed);

        $battle = Battle::create([
            'type' => 'pve',
            'seed' => $seed,
            'battle_version' => $result->version,
            'player_a_id' => $user->id,
            'stage_slug' => "{$region->slug}/{$node['id']}",
            'winner' => $result->winner,
            'result' => $result->jsonSerialize(),
        ]);
        $battle->snapshots()->create(['team' => 'A', 'combatants' => $this->snapshot($teamA)]);
        $battle->snapshots()->create(['team' => 'B', 'combatants' => $this->snapshot($teamB)]);

        $won = $result->winner === 'A';
        $rewards = [];
        if ($won) {
            $rewards = $this->grantRewards($user, $node, $region, $seed);
            $this->awardFighterXp($team, $this->fighterXp($node));
        }

        return ['battle' => $battle, 'won' => $won, 'result' => $result->jsonSerialize(), 'rewards' => $rewards];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function grantRewards(User $user, array $node, RegionDefinition $region, int $seed): array
    {
        $rng = new SeededRng($seed ^ 0x1F2E3D4C);
        $row = (int) $node['row'];
        $type = $node['type'];
        $config = config('regions.battle_rewards');

        $coins = ($config['coins'] + $row * $config['coins_per_row']);
        $xp = $config['xp'] + $row * 4;
        $multiplier = match ($type) {
            'elite' => 1.7,
            'boss' => 3.0,
            default => 1.0,
        };

        $rewards = [
            $this->grant->grant($user, ['type' => 'coins', 'amount' => (int) round($coins * $multiplier)]),
            $this->grant->grant($user, ['type' => 'xp', 'amount' => (int) round($xp * $multiplier)]),
        ];

        $ingredientChance = match ($type) {
            'elite' => 100,
            'boss' => 100,
            default => 28 + $row * 9,
        };
        if ($rng->int(100) < $ingredientChance) {
            $slug = $rng->pick($region->drops['ingredients']);
            $rewards[] = $this->grant->grant($user, ['type' => 'ingredient', 'slug' => $slug, 'qty' => 1 + $rng->int(2)]);
        }

        $canChance = $type === 'boss' ? 100 : $row * 7;
        if ($rng->int(100) < $canChance) {
            $rewards[] = $this->grant->grant($user, ['type' => 'can', 'slug' => $region->drops['cans'][0] ?? 'rusty', 'qty' => 1]);
        }

        $equipChance = match ($type) {
            'boss' => 55,
            'elite' => 25,
            default => 0,
        };
        if ($equipChance > 0 && $rng->int(100) < $equipChance) {
            $rewards[] = $this->grant->grant($user, [
                'type' => 'equipment',
                'slug' => $rng->pick($region->drops['equipment']),
                'rarity' => $type === 'boss' ? 'rare' : ($rng->chance(50) ? 'uncommon' : 'common'),
            ], $rng);
        }

        return array_values(array_filter($rewards));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function fighterXp(array $node): int
    {
        $base = config('regions.battle_rewards.fighter_xp') + (int) $node['row'] * 5;

        return match ($node['type']) {
            'elite' => (int) round($base * 1.6),
            'boss' => (int) round($base * 2.5),
            default => $base,
        };
    }

    public function awardFighterXp(Team $team, int $xp): void
    {
        foreach ($team->members as $member) {
            $fighter = $member->fighter;
            $fighter->xp += $xp;
            $leveled = false;
            while ($fighter->xp >= self::FIGHTER_XP_PER_LEVEL * $fighter->level) {
                $fighter->xp -= self::FIGHTER_XP_PER_LEVEL * $fighter->level;
                $fighter->level++;
                $leveled = true;
            }
            $fighter->save();
            if ($leveled) {
                $this->balance->apply($fighter->fresh(['skills', 'equipment.playerEquipment']));
            }
        }
    }

    /**
     * @param  list<CombatantInput>  $combatants
     * @return list<array<string, mixed>>
     */
    private function snapshot(array $combatants): array
    {
        return array_map(fn (CombatantInput $c) => (array) $c, $combatants);
    }
}
