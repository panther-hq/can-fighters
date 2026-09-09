<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArenaDefenseAttacked implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $defenderId,
        public string $attackerName,
        public bool $defenderWon,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("player.{$this->defenderId}")];
    }

    public function broadcastAs(): string
    {
        return 'arena.defense_attacked';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['attackerName' => $this->attackerName, 'defended' => $this->defenderWon];
    }
}
