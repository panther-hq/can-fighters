<?php

namespace App\Http\Controllers;

use App\Domain\Economy\Shop;
use App\Support\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(private Shop $shop) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->shop->today($request->user()));
    }

    public function buy(Request $request, string $offer): JsonResponse
    {
        $user = $request->user();

        $outcome = Idempotency::run(
            $user,
            "shop.buy.{$offer}",
            $request->header('Idempotency-Key'),
            fn (): array => [200, $this->shop->buy($user, $offer)],
        );

        return response()
            ->json($outcome['body'], $outcome['status'])
            ->header('Idempotency-Replayed', $outcome['replayed'] ? 'true' : 'false');
    }
}
