<?php

namespace App\Domain\Mixer;

/**
 * Every concept — from any provider — passes through here before it can become
 * a fighter (spec §49): allowed class, allowed traits, allowed skill families,
 * then normalisation. Rejects nonsense; trims the rest into legal shape.
 */
class ConceptValidator
{
    public function validate(CharacterConcept $concept): CharacterConcept
    {
        $classes = config('mixer.classes');
        $traits = config('mixer.traits');
        $skillFamilies = config('mixer.skill_families');
        $rarities = config('mixer.rarities');

        $name = trim($concept->name);
        if ($name === '') {
            throw new InvalidConceptException('Concept has no name.');
        }
        $name = mb_substr($name, 0, 60);

        $primaryClass = $concept->primaryClass;
        if (! in_array($primaryClass, $classes, true)) {
            throw new InvalidConceptException("Unknown primary class: {$primaryClass}");
        }

        $secondaryClass = $concept->secondaryClass;
        if ($secondaryClass !== null) {
            if (! in_array($secondaryClass, $classes, true) || $secondaryClass === $primaryClass) {
                $secondaryClass = null; // normalise away an illegal / duplicate secondary
            }
        }

        $legalTraits = array_values(array_unique(array_filter(
            $concept->traits,
            fn ($trait) => in_array($trait, $traits, true),
        )));
        if ($legalTraits === []) {
            throw new InvalidConceptException('Concept has no legal traits.');
        }
        $legalTraits = array_slice($legalTraits, 0, 3);

        $legalSkills = [];
        foreach ($concept->suggestedSkills as $skill) {
            $family = $skill['skillFamily'] ?? null;
            if (is_string($family) && in_array($family, $skillFamilies, true)) {
                $legalSkills[] = [
                    'skillFamily' => $family,
                    'modifier' => isset($skill['modifier']) && is_string($skill['modifier'])
                        ? $skill['modifier']
                        : null,
                ];
            }
        }
        $legalSkills = array_slice($legalSkills, 0, 4);
        if ($legalSkills === []) {
            throw new InvalidConceptException('Concept has no legal skill families.');
        }

        $rarity = in_array($concept->rarity, $rarities, true) ? $concept->rarity : 'common';

        $visualDna = array_map(
            fn ($v) => is_scalar($v) ? (string) $v : '',
            $concept->visualDna,
        );

        return new CharacterConcept(
            name: $name,
            description: trim($concept->description) ?: 'Tajemniczy puszkowy wojownik.',
            primaryClass: $primaryClass,
            secondaryClass: $secondaryClass,
            rarity: $rarity,
            traits: $legalTraits,
            personality: $concept->personality !== null ? mb_substr(trim($concept->personality), 0, 40) : null,
            visualDna: $visualDna,
            suggestedSkills: $legalSkills,
        );
    }
}
