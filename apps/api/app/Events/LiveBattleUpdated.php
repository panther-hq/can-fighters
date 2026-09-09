<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveBattleUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function __construct(
        public int $battleId,
        public int $round,
        public string $status,
        public ?string $winner,
        public array $events,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("battle.{$this->battleId}")];
    }

    public function broadcastAs(): string
    {
        return 'battle.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round' => $this->round,
            'status' => $this->status,
            'winner' => $this->winner,
            'events' => $this->events,
        ];
    }
}
