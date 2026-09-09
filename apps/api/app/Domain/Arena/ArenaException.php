<?php

namespace App\Domain\Arena;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class ArenaException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
