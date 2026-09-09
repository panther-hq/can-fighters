<?php

namespace Tests\Feature\Mixer;

use App\Domain\Mixer\MixerService;
use App\Domain\Mixer\MockCharacterGenerationProvider;
use App\Models\MixRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use MixerConcerns, RefreshDatabase;

    private function queueAMix(): MixRequest
    {
        Queue::fake();
        $user = $this->playerWithIngredients(['battery' => 3, 'screw' => 2]);

        $this->actingAs($user)->postJson('/api/mixer/mix', [
            'ingredients' => [
                ['slug' => 'battery', 'quantity' => 2],
                ['slug' => 'screw', 'quantity' => 1],
            ],
        ])->assertStatus(202);

        return MixRequest::with('user')->firstOrFail();
    }

    public function test_the_mock_provider_is_used_when_configured(): void
    {
        config(['mixer.provider' => 'mock']);
        $mix = $this->queueAMix();

        app(MixerService::class)->process($mix);

        $fighter = $mix->fresh()->resultFighter;
        $this->assertSame('Testowy Puszkowiec', $fighter->name);
        $this->assertSame(1, app(MockCharacterGenerationProvider::class)->calls);
    }

    public function test_generation_falls_back_when_the_provider_keeps_failing(): void
    {
        config(['mixer.provider' => 'mock']);
        app(MockCharacterGenerationProvider::class)->shouldThrow = true;

        $mix = $this->queueAMix();
        app(MixerService::class)->process($mix);

        $mix->refresh();
        // Provider tried twice, then the deterministic fallback took over —
        // the game keeps working (spec §49).
        $this->assertSame(MixRequest::STATUS_COMPLETED, $mix->status);
        $this->assertNotNull($mix->result_fighter_id);
        $this->assertSame(2, app(MockCharacterGenerationProvider::class)->calls);
        $this->assertNotSame('Testowy Puszkowiec', $mix->resultFighter->name);
    }
}
