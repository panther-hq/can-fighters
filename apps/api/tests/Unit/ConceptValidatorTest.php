<?php

namespace Tests\Unit;

use App\Domain\Mixer\CharacterConcept;
use App\Domain\Mixer\ConceptValidator;
use App\Domain\Mixer\InvalidConceptException;
use Tests\TestCase;

class ConceptValidatorTest extends TestCase
{
    private function concept(array $overrides = []): CharacterConcept
    {
        return new CharacterConcept(
            name: $overrides['name'] ?? 'Blaszak',
            description: $overrides['description'] ?? 'Opis.',
            primaryClass: $overrides['primaryClass'] ?? 'mage',
            secondaryClass: $overrides['secondaryClass'] ?? null,
            rarity: $overrides['rarity'] ?? 'rare',
            traits: $overrides['traits'] ?? ['electric', 'metal'],
            personality: $overrides['personality'] ?? 'ponury',
            visualDna: $overrides['visualDna'] ?? ['body' => 'battery'],
            suggestedSkills: $overrides['suggestedSkills'] ?? [
                ['skillFamily' => 'area_damage', 'modifier' => 'electric'],
            ],
        );
    }

    public function test_it_passes_a_clean_concept_through(): void
    {
        $out = (new ConceptValidator)->validate($this->concept());

        $this->assertSame('Blaszak', $out->name);
        $this->assertSame('mage', $out->primaryClass);
    }

    public function test_it_rejects_an_unknown_primary_class(): void
    {
        $this->expectException(InvalidConceptException::class);
        (new ConceptValidator)->validate($this->concept(['primaryClass' => 'necromancer']));
    }

    public function test_it_drops_a_secondary_equal_to_primary(): void
    {
        $out = (new ConceptValidator)->validate($this->concept([
            'primaryClass' => 'mage',
            'secondaryClass' => 'mage',
        ]));

        $this->assertNull($out->secondaryClass);
    }

    public function test_it_filters_out_illegal_traits(): void
    {
        $out = (new ConceptValidator)->validate($this->concept([
            'traits' => ['electric', 'banana', 'metal'],
        ]));

        $this->assertSame(['electric', 'metal'], $out->traits);
    }

    public function test_it_rejects_a_concept_with_no_legal_traits(): void
    {
        $this->expectException(InvalidConceptException::class);
        (new ConceptValidator)->validate($this->concept(['traits' => ['banana', 'nonsense']]));
    }

    public function test_it_drops_illegal_skill_families_and_requires_at_least_one(): void
    {
        $out = (new ConceptValidator)->validate($this->concept([
            'suggestedSkills' => [
                ['skillFamily' => 'nope', 'modifier' => null],
                ['skillFamily' => 'heal', 'modifier' => null],
            ],
        ]));

        $this->assertCount(1, $out->suggestedSkills);
        $this->assertSame('heal', $out->suggestedSkills[0]['skillFamily']);
    }

    public function test_it_normalises_an_unknown_rarity_to_common(): void
    {
        $out = (new ConceptValidator)->validate($this->concept(['rarity' => 'mythic']));

        $this->assertSame('common', $out->rarity);
    }

    public function test_it_truncates_a_long_name(): void
    {
        $out = (new ConceptValidator)->validate($this->concept(['name' => str_repeat('a', 200)]));

        $this->assertSame(60, mb_strlen($out->name));
    }
}
