<?php

namespace Tests\Unit\Battle;

use App\Domain\Battle\ValueObjects\CombatantInput;

trait BattleTestHelpers
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function combatant(array $overrides): CombatantInput
    {
        return new CombatantInput(
            id: $overrides['id'],
            name: $overrides['name'] ?? "U{$overrides['id']}",
            team: $overrides['team'],
            position: $overrides['position'] ?? 'front',
            class: $overrides['class'] ?? 'fighter',
            hp: $overrides['hp'] ?? 120,
            attack: $overrides['attack'] ?? 16,
            defense: $overrides['defense'] ?? 8,
            magic: $overrides['magic'] ?? 6,
            speed: $overrides['speed'] ?? 12,
            crit: $overrides['crit'] ?? 0,
            skills: $overrides['skills'] ?? [],
        );
    }

    /**
     * @param  array<string, int>  $parameters
     * @return array{slot: int, family: string, parameters: array<string, int>}
     */
    protected function skill(string $family, array $parameters = [], int $slot = 0): array
    {
        return ['slot' => $slot, 'family' => $family, 'parameters' => $parameters];
    }
}
