<?php

namespace App\Domain\Economy;

use App\Models\IngredientDefinition;
use App\Models\PlayerShop;
use App\Models\User;
use App\Support\SeededRng;
use Illuminate\Support\Facades\DB;

/**
 * Per-player daily shop. Stock is rolled deterministically from the player id
 * + the date, so a reload shows the same offers until midnight.
 */
class Shop
{
    public function __construct(private GrantReward $grant) {}

    /**
     * @return array<string, mixed>
     */
    public function today(User $user): array
    {
        $shop = PlayerShop::query()->firstOrCreate(['user_id' => $user->id], ['offers' => []]);
        $today = now()->toDateString();

        if ($shop->generated_on?->toDateString() !== $today) {
            $shop->update(['generated_on' => $today, 'offers' => $this->roll($user, $today)]);
        }

        return ['restockedOn' => $today, 'offers' => $shop->offers];
    }

    /**
     * @return array<string, mixed>
     */
    public function buy(User $user, string $offerId): array
    {
        return DB::transaction(function () use ($user, $offerId): array {
            $shop = PlayerShop::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $offers = $shop->offers;
            $index = collect($offers)->search(fn ($o) => $o['id'] === $offerId);
            if ($index === false) {
                throw new EconomyException('Nie ma takiej oferty.');
            }
            $offer = $offers[$index];
            if ($offer['bought'] ?? false) {
                throw new EconomyException('Ta oferta jest już wykupiona.');
            }

            $profile = $user->playerProfile()->lockForUpdate()->first();
            if ($profile->coins < $offer['price']) {
                throw new EconomyException('Za mało monet.');
            }
            $profile->decrement('coins', $offer['price']);

            $reward = $this->grant->grant($user, $offer['grant']);

            $offers[$index]['bought'] = true;
            $shop->update(['offers' => $offers]);

            return ['offer' => $offers[$index], 'reward' => $reward, 'coins' => $profile->fresh()->coins];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roll(User $user, string $date): array
    {
        $rng = new SeededRng($user->id * 1_000_003 + (int) str_replace('-', '', $date));
        $offers = [];

        if ($rng->chance((int) config('shop.can.chance'))) {
            $offers[] = [
                'id' => 'can-'.count($offers),
                'kind' => 'can',
                'label' => 'Zardzewiała puszka',
                'icon' => '🥫',
                'price' => (int) config('shop.can.price'),
                'grant' => ['type' => 'can', 'slug' => config('shop.can.slug'), 'qty' => 1],
                'bought' => false,
            ];
        }

        $ingredients = IngredientDefinition::query()->get();
        $slots = max(1, (int) config('shop.offer_count') - count($offers) - 1);
        for ($i = 0; $i < $slots; $i++) {
            /** @var IngredientDefinition $ingredient */
            $ingredient = $ingredients[$rng->int($ingredients->count())];
            $qty = (int) config('shop.ingredient.min_qty')
                + $rng->int((int) config('shop.ingredient.max_qty') - (int) config('shop.ingredient.min_qty') + 1);
            $unit = (int) (config('shop.ingredient.price_per_unit')[$ingredient->rarity] ?? 14);

            $offers[] = [
                'id' => 'ing-'.$i,
                'kind' => 'ingredient',
                'label' => "{$ingredient->name} ×{$qty}",
                'icon' => $ingredient->icon,
                'price' => $unit * $qty,
                'grant' => ['type' => 'ingredient', 'slug' => $ingredient->slug, 'qty' => $qty],
                'bought' => false,
            ];
        }

        if ($rng->chance((int) config('shop.equipment.chance'))) {
            $rarity = $rng->pick(config('shop.equipment.rarities'));
            $offers[] = [
                'id' => 'eq-'.count($offers),
                'kind' => 'equipment',
                'label' => 'Losowy przedmiot ('.$rarity.')',
                'icon' => '🛠️',
                'price' => (int) (config('shop.equipment.price')[$rarity] ?? 300),
                'grant' => ['type' => 'equipment', 'rarity' => $rarity],
                'bought' => false,
            ];
        }

        return $offers;
    }
}
