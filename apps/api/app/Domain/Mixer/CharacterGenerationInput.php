<?php

namespace App\Domain\Mixer;

final readonly class CharacterGenerationInput
{
    /**
     * @param  list<IngredientSpec>  $ingredients
     */
    public function __construct(
        public array $ingredients,
        public int $seed,
    ) {}

    /**
     * Tags weighted by how much of each ingredient went in, highest first.
     *
     * @return array<string, int>
     */
    public function dominantTags(): array
    {
        $weights = [];

        foreach ($this->ingredients as $ingredient) {
            foreach ($ingredient->tags as $tag) {
                $weights[$tag] = ($weights[$tag] ?? 0) + $ingredient->quantity;
            }
        }

        arsort($weights);

        return $weights;
    }

    /**
     * Ingredient slugs weighted by quantity, highest first.
     *
     * @return array<string, int>
     */
    public function dominantIngredients(): array
    {
        $weights = [];

        foreach ($this->ingredients as $ingredient) {
            $weights[$ingredient->slug] = ($weights[$ingredient->slug] ?? 0) + $ingredient->quantity;
        }

        arsort($weights);

        return $weights;
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (IngredientSpec $i) => $i->quantity, $this->ingredients));
    }

    /**
     * @return list<string>
     */
    public function rarities(): array
    {
        return array_map(fn (IngredientSpec $i) => $i->rarity, $this->ingredients);
    }
}
