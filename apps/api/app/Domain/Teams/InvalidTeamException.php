<?php

namespace App\Domain\Teams;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidTeamException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
