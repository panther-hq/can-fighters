<?php

namespace App\Domain\Inventory;

final readonly class CanOpeningResult
{
    /**
     * @param  list<array{slug: string, name: string, icon: string, quantity: int}>  $received
     */
    public function __construct(
        public int $openingId,
        public int $seed,
        public array $received,
    ) {}
}
