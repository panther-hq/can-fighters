<?php

namespace App\Events;

use App\Models\MixRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MixerFailed implements ShouldBroadcast
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
        return 'mixer.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'mixId' => $this->mix->id,
            'error' => $this->mix->error,
        ];
    }
}
