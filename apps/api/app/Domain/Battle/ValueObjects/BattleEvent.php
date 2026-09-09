<?php

namespace App\Domain\Battle\ValueObjects;

/**
 * One ordered thing that happened in a battle (spec §34). The frontend only
 * visualises these — it never computes damage or the winner.
 */
final readonly class BattleEvent implements \JsonSerializable
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public int $sequence,
        public int $time,
        public string $type,
        public ?int $source = null,
        public ?int $target = null,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'sequence' => $this->sequence,
            'time' => $this->time,
            'type' => $this->type,
            'source' => $this->source,
            'target' => $this->target,
            ...$this->extra,
        ], fn ($value) => $value !== null);
    }
}
