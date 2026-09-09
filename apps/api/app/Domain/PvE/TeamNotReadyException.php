<?php

namespace App\Domain\PvE;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class TeamNotReadyException extends RuntimeException
{
    public function __construct(string $message = 'Najpierw ustaw drużynę.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
