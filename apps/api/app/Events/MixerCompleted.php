<?php

namespace App\Events;

use App\Models\MixRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MixerCompleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public MixRequest $mix) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("player.{$this->mix->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'mixer.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'mixId' => $this->mix->id,
            'fighterId' => $this->mix->result_fighter_id,
        ];
    }
}
