<?php

namespace App\Domain\PvE;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class StageLockedException extends RuntimeException
{
    public function __construct(string $message = 'Ten etap jest jeszcze zablokowany.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
