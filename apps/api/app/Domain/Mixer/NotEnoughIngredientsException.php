<?php

namespace App\Domain\Mixer;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class NotEnoughIngredientsException extends RuntimeException
{
    public function __construct(string $message = 'Nie masz wystarczających składników.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
