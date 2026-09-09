<?php

namespace App\Domain\Mixer;

/**
 * The creative layer's output (spec §8, §10). Numbers are NOT decided here —
 * the Balance Engine (phase 5) turns this concept into stats.
 */
final readonly class CharacterConcept
{
    /**
     * @param  list<string>  $traits
     * @param  array<string, string>  $visualDna
     * @param  list<array{skillFamily: string, modifier: string|null}>  $suggestedSkills
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $primaryClass,
        public ?string $secondaryClass,
        public string $rarity,
        public array $traits,
        public ?string $personality,
        public array $visualDna,
        public array $suggestedSkills,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            $overrides['name'] ?? $this->name,
            $overrides['description'] ?? $this->description,
            $overrides['primaryClass'] ?? $this->primaryClass,
            array_key_exists('secondaryClass', $overrides) ? $overrides['secondaryClass'] : $this->secondaryClass,
            $overrides['rarity'] ?? $this->rarity,
            $overrides['traits'] ?? $this->traits,
            array_key_exists('personality', $overrides) ? $overrides['personality'] : $this->personality,
            $overrides['visualDna'] ?? $this->visualDna,
            $overrides['suggestedSkills'] ?? $this->suggestedSkills,
        );
    }
}
