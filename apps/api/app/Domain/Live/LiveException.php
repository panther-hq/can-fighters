<?php

namespace App\Domain\Live;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class LiveException extends RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
