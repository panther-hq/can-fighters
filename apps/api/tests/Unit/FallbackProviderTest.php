<?php

namespace Tests\Unit;

use App\Domain\Mixer\CharacterGenerationInput;
use App\Domain\Mixer\FallbackCharacterGenerationProvider;
use App\Domain\Mixer\IngredientSpec;
use Tests\TestCase;

class FallbackProviderTest extends TestCase
{
    private function input(int $seed): CharacterGenerationInput
    {
        return new CharacterGenerationInput([
            new IngredientSpec('screw', 'Śruba', 3, ['mechanical', 'armor'], 'common'),
            new IngredientSpec('fork', 'Widelec', 1, ['weapon', 'melee', 'crit'], 'uncommon'),
        ], $seed);
    }

    public function test_it_is_deterministic_for_a_seed(): void
    {
        $a = (new FallbackCharacterGenerationProvider)->generate($this->input(4242));
        $b = (new FallbackCharacterGenerationProvider)->generate($this->input(4242));

        $this->assertEquals($a, $b);
    }

    public function test_class_follows_the_dominant_tag(): void
    {
        // "mechanical" dominates (screw ×3) -> engineer per config map
        $concept = (new FallbackCharacterGenerationProvider)->generate($this->input(1));

        $this->assertSame('engineer', $concept->primaryClass);
        $this->assertContains('mechanical', $concept->traits);
        $this->assertNotSame('', $concept->name);
    }

    public function test_different_seeds_can_produce_different_names(): void
    {
        $names = [];
        foreach (range(1, 8) as $seed) {
            $names[] = (new FallbackCharacterGenerationProvider)->generate($this->input($seed))->name;
        }

        $this->assertGreaterThan(1, count(array_unique($names)));
    }
}
