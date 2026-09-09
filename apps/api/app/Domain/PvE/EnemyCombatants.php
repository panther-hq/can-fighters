<?php

namespace App\Domain\PvE;

use App\Domain\Balance\SkillParameterResolver;
use App\Domain\Balance\StandardBalanceEngine;
use App\Domain\Battle\ValueObjects\CombatantInput;

/**
 * Builds engine input for a node's enemies. Enemies are not Fighter models —
 * their stats come from the class weight profile at the node's budget, so PvE
 * difficulty reuses the same balance maths.
 */
class EnemyCombatants
{
    public function __construct(
        private StandardBalanceEngine $balance,
        private SkillParameterResolver $skills,
    ) {}

    /**
     * @param  list<array{name: string, class: string, position?: string}>  $enemies
     * @return list<CombatantInput>
     */
    public function forSpec(array $enemies, int $budget): array
    {
        $positions = ['front', 'middle', 'back'];
        $out = [];
        $index = 0;

        foreach ($enemies as $enemy) {
            $index++;
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
                position: $enemy['position'] ?? $positions[($index - 1) % 3],
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
