<?php

namespace Tests\Feature\Mixer;

use App\Domain\Mixer\MixerService;
use App\Jobs\ProcessMixRequest;
use App\Models\MixRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MixTest extends TestCase
{
    use MixerConcerns, RefreshDatabase;

    private function mixBody(): array
    {
        return [
            'ingredients' => [
                ['slug' => 'battery', 'quantity' => 2],
                ['slug' => 'screw', 'quantity' => 1],
            ],
        ];
    }

    public function test_mix_requires_authentication(): void
    {
        $this->postJson('/api/mixer/mix', $this->mixBody())->assertUnauthorized();
    }

    public function test_mix_consumes_ingredients_and_queues_generation(): void
    {
        Queue::fake();
        $user = $this->playerWithIngredients(['battery' => 3, 'screw' => 2]);

        $this->actingAs($user)
            ->postJson('/api/mixer/mix', $this->mixBody())
            ->assertStatus(202)
            ->assertJsonPath('status', MixRequest::STATUS_PROCESSING);

        $this->assertSame(1, $user->ingredients()->whereRelation('definition', 'slug', 'battery')->value('quantity'));
        $this->assertSame(1, $user->ingredients()->whereRelation('definition', 'slug', 'screw')->value('quantity'));

        Queue::assertPushed(ProcessMixRequest::class);
    }

    public function test_processing_a_request_creates_the_fighter(): void
    {
        Queue::fake();
        $user = $this->playerWithIngredients(['battery' => 3, 'screw' => 2]);

        $this->actingAs($user)->postJson('/api/mixer/mix', $this->mixBody())->assertStatus(202);
        $mix = MixRequest::firstOrFail();

        app(MixerService::class)->process($mix->fresh('user'));

        $mix->refresh();
        $this->assertSame(MixRequest::STATUS_COMPLETED, $mix->status);
        $this->assertNotNull($mix->result_fighter_id);
        $this->assertSame(1, $user->fighters()->count());

        $this->actingAs($user)
            ->getJson("/api/mixer/{$mix->id}")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('fighter.id', $mix->result_fighter_id);
    }

    public function test_not_enough_ingredients_is_rejected_and_consumes_nothing(): void
    {
        Queue::fake();
        $user = $this->playerWithIngredients(['battery' => 1, 'screw' => 1]);

        $this->actingAs($user)
            ->postJson('/api/mixer/mix', $this->mixBody())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nie masz wystarczających składników.');

        $this->assertSame(1, $user->ingredients()->whereRelation('definition', 'slug', 'battery')->value('quantity'));
        $this->assertDatabaseCount('mix_requests', 0);
        Queue::assertNothingPushed();
    }

    public function test_same_idempotency_key_does_not_start_a_second_mix(): void
    {
        Queue::fake();
        $user = $this->playerWithIngredients(['battery' => 6, 'screw' => 4]);

        $first = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'mix-key-1')
            ->postJson('/api/mixer/mix', $this->mixBody())
            ->assertStatus(202)
            ->assertHeader('Idempotency-Replayed', 'false');

        $second = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'mix-key-1')
            ->postJson('/api/mixer/mix', $this->mixBody())
            ->assertStatus(202)
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('mixId'), $second->json('mixId'));
        $this->assertDatabaseCount('mix_requests', 1);
        // only one mix worth of ingredients consumed
        $this->assertSame(4, $user->ingredients()->whereRelation('definition', 'slug', 'battery')->value('quantity'));
    }

    public function test_cannot_view_someone_elses_mix(): void
    {
        Queue::fake();
        $user = $this->playerWithIngredients(['battery' => 3, 'screw' => 2]);
        $this->actingAs($user)->postJson('/api/mixer/mix', $this->mixBody())->assertStatus(202);
        $mix = MixRequest::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/mixer/{$mix->id}")
            ->assertNotFound();
    }
}
