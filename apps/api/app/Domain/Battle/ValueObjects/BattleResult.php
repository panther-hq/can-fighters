<?php

namespace App\Domain\Battle\ValueObjects;

final readonly class BattleResult implements \JsonSerializable
{
    /**
     * @param  'A'|'B'|'draw'  $winner
     * @param  list<BattleEvent>  $events
     * @param  list<int>  $survivorsA
     * @param  list<int>  $survivorsB
     */
    public function __construct(
        public string $winner,
        public int $duration,
        public array $events,
        public array $survivorsA,
        public array $survivorsB,
        public int $seed,
        public int $version,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'winner' => $this->winner,
            'duration' => $this->duration,
            'seed' => $this->seed,
            'version' => $this->version,
            'survivors' => ['A' => $this->survivorsA, 'B' => $this->survivorsB],
            'events' => $this->events,
        ];
    }
}
