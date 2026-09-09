<?php

namespace App\Domain\Mixer;

use App\Support\SeededRng;

/**
 * Deterministic concept generator — no external calls, always available.
 * This is the default provider and the safety net if a real AI provider fails
 * (spec §49, §72 rule 19).
 */
class FallbackCharacterGenerationProvider implements CharacterGenerationProvider
{
    public function generate(CharacterGenerationInput $input): CharacterConcept
    {
        $rng = new SeededRng($input->seed);

        $tags = array_keys($input->dominantTags());
        $topTag = $tags[0] ?? 'chaos';
        $secondTag = $tags[1] ?? null;

        $tagClassMap = config('mixer.tag_class_map');
        $primaryClass = $tagClassMap[$topTag] ?? 'fighter';

        $secondaryClass = null;
        if ($secondTag !== null && isset($tagClassMap[$secondTag])) {
            $candidate = $tagClassMap[$secondTag];
            if ($candidate !== $primaryClass && $rng->chance(35)) {
                $secondaryClass = $candidate;
            }
        }

        $traits = array_values(array_slice(array_intersect($tags, config('mixer.traits')), 0, 3));
        if ($traits === []) {
            $traits = ['chaos'];
        }

        $skillFamilies = config("mixer.class_skills.{$primaryClass}", ['direct_damage', 'shield']);
        $suggestedSkills = [
            ['skillFamily' => $skillFamilies[0], 'modifier' => $topTag],
            ['skillFamily' => $skillFamilies[1], 'modifier' => $secondTag],
        ];

        $dominantSlug = array_key_first($input->dominantIngredients()) ?? 'tin_can';

        return new CharacterConcept(
            name: $this->buildName($rng, $dominantSlug),
            description: $this->buildDescription($input, $primaryClass),
            primaryClass: $primaryClass,
            secondaryClass: $secondaryClass,
            rarity: $this->rollRarity($rng, $input),
            traits: $traits,
            personality: $rng->pick(config('mixer.personalities')),
            visualDna: [
                'body' => $dominantSlug,
                'accent' => $tags[1] ?? $topTag,
                'effect' => $topTag,
            ],
            suggestedSkills: $suggestedSkills,
        );
    }

    private function buildName(SeededRng $rng, string $dominantSlug): string
    {
        $prefix = $rng->pick(config('mixer.name_prefixes'));
        $core = config("mixer.name_cores.{$dominantSlug}", 'Puszkowiec');
        $suffix = $rng->pick(config('mixer.name_suffixes'));

        return trim("{$prefix} {$core} {$suffix}");
    }

    private function buildDescription(CharacterGenerationInput $input, string $primaryClass): string
    {
        $names = array_map(fn (IngredientSpec $i) => mb_strtolower($i->name), $input->ingredients);
        $list = implode(', ', $names);

        return "Powstał z miksu ({$list}). Walczy jako {$this->classLabel($primaryClass)}.";
    }

    private function classLabel(string $class): string
    {
        return [
            'tank' => 'obrońca',
            'fighter' => 'wojownik',
            'assassin' => 'zabójca',
            'ranged' => 'strzelec',
            'mage' => 'mag',
            'support' => 'wsparcie',
            'debuffer' => 'osłabiacz',
            'summoner' => 'przywoływacz',
            'engineer' => 'inżynier',
            'crafter' => 'rzemieślnik',
        ][$class] ?? $class;
    }

    private function rollRarity(SeededRng $rng, CharacterGenerationInput $input): string
    {
        $score = 0;
        foreach ($input->rarities() as $rarity) {
            $score += match ($rarity) {
                'legendary' => 8,
                'epic' => 5,
                'rare' => 3,
                'uncommon' => 1,
                default => 0,
            };
        }

        $score += $rng->int(4);

        return match (true) {
            $score >= 14 => 'legendary',
            $score >= 10 => 'epic',
            $score >= 6 => 'rare',
            $score >= 3 => 'uncommon',
            default => 'common',
        };
    }
}
