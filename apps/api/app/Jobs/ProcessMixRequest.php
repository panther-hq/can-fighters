<?php

namespace App\Jobs;

use App\Domain\Mixer\MixerService;
use App\Models\MixRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMixRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $mixRequestId) {}

    public function handle(MixerService $mixer): void
    {
        $mix = MixRequest::with('user')->find($this->mixRequestId);

        if ($mix !== null) {
            $mixer->process($mix);
        }
    }
}
