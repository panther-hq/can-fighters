<?php

namespace App\Domain\Inventory;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class NotEnoughCansException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Nie masz tej puszki.'], 422);
    }
}
