<?php

namespace App\Http\Controllers;

use App\Domain\Economy\DailyReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyController extends Controller
{
    public function __construct(private DailyReward $daily) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->daily->status($request->user()));
    }

    public function claim(Request $request): JsonResponse
    {
        return response()->json($this->daily->claim($request->user()));
    }
}
