<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveMatchFound implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $playerId, public int $battleId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("player.{$this->playerId}")];
    }

    public function broadcastAs(): string
    {
        return 'matchmaking.found';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['battleId' => $this->battleId];
    }
}
