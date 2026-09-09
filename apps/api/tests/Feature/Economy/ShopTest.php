<?php

namespace Tests\Feature\Economy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function player(int $coins = 5000): User
    {
        $user = User::factory()->create();
        $user->playerProfile()->create(['coins' => $coins]);

        return $user;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/shop')->assertUnauthorized();
    }

    public function test_the_shop_is_stable_for_the_day(): void
    {
        $user = $this->player();

        $first = $this->actingAs($user)->getJson('/api/shop')
            ->assertOk()
            ->assertJsonStructure(['restockedOn', 'offers' => [['id', 'kind', 'price', 'grant', 'bought']]]);

        $second = $this->actingAs($user)->getJson('/api/shop');
        $this->assertSame($first->json('offers'), $second->json('offers'));
    }

    public function test_buying_an_offer_spends_coins_and_grants_the_item(): void
    {
        $user = $this->player(5000);
        $offers = $this->actingAs($user)->getJson('/api/shop')->json('offers');
        $ingredient = collect($offers)->firstWhere('kind', 'ingredient');

        $this->actingAs($user)
            ->postJson("/api/shop/{$ingredient['id']}/buy")
            ->assertOk()
            ->assertJsonPath('offer.bought', true)
            ->assertJsonPath('reward.type', 'ingredient');

        $this->assertSame(5000 - $ingredient['price'], $user->playerProfile->fresh()->coins);
        $this->assertGreaterThan(0, $user->ingredients()->sum('quantity'));

        $this->actingAs($user)
            ->postJson("/api/shop/{$ingredient['id']}/buy")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ta oferta jest już wykupiona.');
    }

    public function test_you_cannot_buy_without_enough_coins(): void
    {
        $user = $this->player(0);
        $offer = $this->actingAs($user)->getJson('/api/shop')->json('offers.0');

        $this->actingAs($user)
            ->postJson("/api/shop/{$offer['id']}/buy")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Za mało monet.');
    }

    public function test_the_shop_restocks_the_next_day(): void
    {
        $user = $this->player();
        $day1 = $this->actingAs($user)->getJson('/api/shop')->json('offers');

        $this->travel(1)->day();
        $day2 = $this->actingAs($user)->getJson('/api/shop')->json('offers');

        $this->assertNotSame($day1, $day2);
    }
}
