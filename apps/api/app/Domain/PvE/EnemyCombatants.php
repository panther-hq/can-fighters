<?php

namespace App\Domain\PvE;

use App\Domain\Balance\SkillParameterResolver;
use App\Domain\Balance\StandardBalanceEngine;
use App\Domain\Battle\ValueObjects\CombatantInput;
use App\Models\PveStageDefinition;

/**
 * Builds engine input for a stage's enemies. Enemies are not Fighter models —
 * their stats come from the class weight profile at the stage's enemy budget
 * (bosses get a bump), so PvE difficulty reuses the same balance maths.
 */
class EnemyCombatants
{
    public function __construct(
        private StandardBalanceEngine $balance,
        private SkillParameterResolver $skills,
    ) {}

    /**
     * @return list<CombatantInput>
     */
    public function forStage(PveStageDefinition $stage): array
    {
        $out = [];
        $index = 0;

        foreach ($stage->enemies as $enemy) {
            $index++;
            $budget = $stage->enemy_budget;
            if ($stage->is_boss && $index === 1) {
                $budget = (int) round($budget * 1.6);
            }

            $stats = $this->balance->statsForBudget($budget, $enemy['class']);

            $families = config("mixer.class_skills.{$enemy['class']}", ['direct_damage', 'shield']);
            $skills = [];
            foreach ($families as $slot => $family) {
                $skills[] = [
                    'slot' => $slot,
                    'family' => $family,
                    'parameters' => $this->skills->resolve($family, 1, $stats),
                ];
            }

            $out[] = new CombatantInput(
                id: -$index,
                name: $enemy['name'],
                team: 'B',
                position: $enemy['position'],
                class: $enemy['class'],
                hp: $stats['hp'],
                attack: $stats['attack'],
                defense: $stats['defense'],
                magic: $stats['magic'],
                speed: $stats['speed'],
                crit: $stats['crit'],
                skills: $skills,
            );
        }

        return $out;
    }
}
