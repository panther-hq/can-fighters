<?php

namespace App\Domain\Fighters;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class NotEnoughCoinsException extends RuntimeException
{
    public function __construct(string $message = 'Za mało monet.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
