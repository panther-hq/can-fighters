<?php

namespace App\Domain\Battle\ValueObjects;

/**
 * The engine's per-fighter input. Built from Fighter models by
 * App\Domain\Battle\FighterCombatants (PvE / PvP) or hand-made in tests.
 */
final readonly class CombatantInput
{
    /**
     * @param  'A'|'B'  $team
     * @param  list<array{slot: int, family: string, parameters: array<string, int>}>  $skills
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $team,
        public string $position,
        public string $class,
        public int $hp,
        public int $attack,
        public int $defense,
        public int $magic,
        public int $speed,
        public int $crit,
        public array $skills = [],
    ) {}
}
