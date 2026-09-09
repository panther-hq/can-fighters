<?php

namespace App\Domain\Economy;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class EconomyException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
