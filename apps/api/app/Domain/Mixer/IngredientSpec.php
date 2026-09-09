<?php

namespace App\Domain\Mixer;

final readonly class IngredientSpec
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $slug,
        public string $name,
        public int $quantity,
        public array $tags,
        public string $rarity,
    ) {}
}
