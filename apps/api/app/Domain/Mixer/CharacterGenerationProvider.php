<?php

namespace App\Domain\Mixer;

/**
 * Keeps game logic independent of any single AI vendor (spec §48).
 * Implementations must be deterministic for a given input seed where possible.
 */
interface CharacterGenerationProvider
{
    public function generate(CharacterGenerationInput $input): CharacterConcept;
}
