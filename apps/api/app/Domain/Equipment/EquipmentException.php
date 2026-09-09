<?php

namespace App\Domain\Equipment;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class EquipmentException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
