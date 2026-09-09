<?php

namespace App\Domain\Mixer;

/**
 * Test double. Returns a fixed concept, or whatever `$next` is set to, or
 * throws when `$shouldThrow` is set (to exercise the retry / fallback path).
 */
class MockCharacterGenerationProvider implements CharacterGenerationProvider
{
    public ?CharacterConcept $next = null;

    public bool $shouldThrow = false;

    public int $calls = 0;

    public function generate(CharacterGenerationInput $input): CharacterConcept
    {
        $this->calls++;

        if ($this->shouldThrow) {
            throw new \RuntimeException('mock provider failure');
        }

        return $this->next ?? new CharacterConcept(
            name: 'Testowy Puszkowiec',
            description: 'Wygenerowany przez mock.',
            primaryClass: 'fighter',
            secondaryClass: null,
            rarity: 'common',
            traits: ['mechanical', 'metal'],
            personality: 'spokojny',
            visualDna: ['body' => 'tin_can', 'accent' => 'metal', 'effect' => 'mechanical'],
            suggestedSkills: [
                ['skillFamily' => 'direct_damage', 'modifier' => null],
                ['skillFamily' => 'shield', 'modifier' => null],
            ],
        );
    }
}
