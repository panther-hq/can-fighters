<?php

namespace App\Domain\PvE;

use App\Domain\Balance\StandardBalanceEngine;
use App\Domain\Battle\BattleEngine;
use App\Domain\Battle\FighterCombatants;
use App\Domain\Battle\ValueObjects\BattleResult;
use App\Domain\Battle\ValueObjects\CombatantInput;
use App\Models\Battle;
use App\Models\CanDefinition;
use App\Models\IngredientDefinition;
use App\Models\PlayerProfile;
use App\Models\PveStageDefinition;
use App\Models\Team;
use App\Models\User;
use App\Support\SeededRng;
use Illuminate\Support\Facades\DB;

/**
 * Runs one PvE stage battle end to end: build teams, simulate, persist the
 * battle + snapshots, and (on a win) grant rewards, fighter XP and progress.
 * Atomic and reward-locked (spec §56–§57).
 */
class FightStage
{
    private const ACCOUNT_XP_PER_LEVEL = 120;

    private const FIGHTER_XP_PER_LEVEL = 100;

    public function __construct(
        private BattleEngine $engine,
        private FighterCombatants $fighterCombatants,
        private EnemyCombatants $enemyCombatants,
        private StandardBalanceEngine $balance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fight(User $user, PveStageDefinition $stage): array
    {
        return DB::transaction(function () use ($user, $stage): array {
            $this->assertUnlocked($user, $stage);

            $team = $user->teams()
                ->where('type', 'campaign')
                ->with(['members.fighter.stats', 'members.fighter.skills'])
                ->first();

            if ($team === null || $team->members->isEmpty()) {
                throw new TeamNotReadyException;
            }

            $entries = $team->members->map(fn ($m) => ['fighter' => $m->fighter, 'position' => $m->position]);
            $teamA = $this->fighterCombatants->fromEntries($entries, 'A');
            $teamB = $this->enemyCombatants->forStage($stage);

            $seed = random_int(1, PHP_INT_MAX);
            $result = $this->engine->run([...$teamA, ...$teamB], $seed);

            $battle = Battle::create([
                'type' => 'pve',
                'seed' => $seed,
                'battle_version' => $result->version,
                'player_a_id' => $user->id,
                'stage_slug' => $stage->slug,
                'winner' => $result->winner,
                'result' => $result->jsonSerialize(),
            ]);
            $battle->snapshots()->create(['team' => 'A', 'combatants' => $this->snapshot($teamA)]);
            $battle->snapshots()->create(['team' => 'B', 'combatants' => $this->snapshot($teamB)]);

            $won = $result->winner === 'A';
            $stars = 0;
            $rewards = ['coins' => 0, 'xp' => 0, 'ingredients' => [], 'cans' => []];

            if ($won) {
                $stars = $this->stars($result, count($teamA));
                $rewards = $this->grantRewards($user, $stage, $seed);
                $this->awardFighterXp($team, (int) ($stage->rewards['fighterXp'] ?? 0));
                $this->recordProgress($user, $stage, $stars, $battle->id);
            }

            return [
                'battleId' => $battle->id,
                'won' => $won,
                'stars' => $stars,
                'rewards' => $rewards,
                'result' => $result->jsonSerialize(),
            ];
        });
    }

    private function assertUnlocked(User $user, PveStageDefinition $stage): void
    {
        if ($stage->order <= 1) {
            return;
        }

        $previous = PveStageDefinition::query()
            ->where('region', $stage->region)
            ->where('order', $stage->order - 1)
            ->first();

        if ($previous === null) {
            return;
        }

        $cleared = $user->stageProgress()
            ->where('stage_slug', $previous->slug)
            ->where('stars', '>=', 1)
            ->exists();

        if (! $cleared) {
            throw new StageLockedException;
        }
    }

    private function stars(BattleResult $result, int $teamSize): int
    {
        $alive = count($result->survivorsA);

        return match (true) {
            $alive >= $teamSize => 3,
            $alive * 2 >= $teamSize => 2,
            default => 1,
        };
    }

    /**
     * @return array{coins: int, xp: int, ingredients: list<array<string, mixed>>, cans: list<array<string, mixed>>}
     */
    private function grantRewards(User $user, PveStageDefinition $stage, int $seed): array
    {
        $rng = new SeededRng($seed ^ 0x5F3759DF);
        $rewards = $stage->rewards;

        $profile = $user->playerProfile()->lockForUpdate()->first();
        $profile->increment('coins', (int) $rewards['coins']);
        $profile->increment('xp', (int) $rewards['xp']);
        $this->levelUpAccount($profile);

        $ingredients = [];
        foreach ($rewards['ingredientDrops'] ?? [] as $drop) {
            if ($rng->int(100) >= (int) $drop['chance']) {
                continue;
            }
            $definition = IngredientDefinition::firstWhere('slug', $drop['slug']);
            if ($definition === null) {
                continue;
            }
            $min = (int) ($drop['min'] ?? 1);
            $max = (int) ($drop['max'] ?? $min);
            $qty = $min + $rng->int(max(1, $max - $min + 1));

            $row = $user->ingredients()->firstOrCreate(['ingredient_definition_id' => $definition->id], ['quantity' => 0]);
            $row->increment('quantity', $qty);
            $ingredients[] = ['slug' => $definition->slug, 'name' => $definition->name, 'icon' => $definition->icon, 'quantity' => $qty];
        }

        $cans = [];
        foreach ($rewards['canDrops'] ?? [] as $drop) {
            if ($rng->int(100) >= (int) $drop['chance']) {
                continue;
            }
            $definition = CanDefinition::firstWhere('slug', $drop['slug']);
            if ($definition === null) {
                continue;
            }
            $row = $user->cans()->firstOrCreate(['can_definition_id' => $definition->id], ['quantity' => 0]);
            $row->increment('quantity', 1);
            $cans[] = ['slug' => $definition->slug, 'name' => $definition->name, 'icon' => $definition->icon, 'quantity' => 1];
        }

        return ['coins' => (int) $rewards['coins'], 'xp' => (int) $rewards['xp'], 'ingredients' => $ingredients, 'cans' => $cans];
    }

    private function levelUpAccount(PlayerProfile $profile): void
    {
        while ($profile->xp >= self::ACCOUNT_XP_PER_LEVEL * $profile->level) {
            $profile->xp -= self::ACCOUNT_XP_PER_LEVEL * $profile->level;
            $profile->level++;
        }
        $profile->save();
    }

    private function awardFighterXp(Team $team, int $xp): void
    {
        if ($xp <= 0) {
            return;
        }

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
                $this->balance->apply($fighter->fresh(['skills']));
            }
        }
    }

    private function recordProgress(User $user, PveStageDefinition $stage, int $stars, int $battleId): void
    {
        $progress = $user->stageProgress()->firstOrNew(['stage_slug' => $stage->slug]);
        $progress->stars = max((int) $progress->stars, $stars);
        $progress->cleared_at ??= now();
        $progress->best_battle_id = $battleId;
        $progress->save();
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
