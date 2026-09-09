<?php

namespace App\Domain\Battle;

use App\Domain\Battle\ValueObjects\CombatantInput;
use App\Models\Fighter;
use Illuminate\Support\Collection;

/**
 * Builds engine input from Fighter models. Used by PvE (phase 7) and the
 * Arena (phase 9); an immutable snapshot at battle time so results stay
 * reproducible even if the fighter is later changed (spec §29, §35).
 */
class FighterCombatants
{
    /**
     * @param  Collection<int, array{fighter: Fighter, position: string}>  $entries
     * @param  'A'|'B'  $team
     * @return list<CombatantInput>
     */
    public function fromEntries(Collection $entries, string $team): array
    {
        return $entries->map(function (array $entry) use ($team): CombatantInput {
            /** @var Fighter $fighter */
            $fighter = $entry['fighter'];
            $stats = $fighter->stats;

            return new CombatantInput(
                id: $fighter->id,
                name: $fighter->name,
                team: $team,
                position: $entry['position'],
                class: $fighter->primary_class,
                hp: (int) ($stats->hp ?? 1),
                attack: (int) ($stats->attack ?? 1),
                defense: (int) ($stats->defense ?? 1),
                magic: (int) ($stats->magic ?? 1),
                speed: (int) ($stats->speed ?? 1),
                crit: (int) ($stats->crit ?? 0),
                skills: $fighter->skills->map(fn ($skill) => [
                    'slot' => (int) $skill->slot,
                    'family' => $skill->skill_family,
                    'parameters' => array_map('intval', $skill->parameters ?? []),
                ])->values()->all(),
            );
        })->values()->all();
    }
}
