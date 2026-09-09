<?php

namespace App\Http\Resources;

use App\Models\MixRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MixRequest
 */
class MixRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mixId' => $this->id,
            'status' => $this->status,
            'error' => $this->error,
            'fighter' => $this->resultFighter
                ? FighterResource::make($this->resultFighter)
                : null,
        ];
    }
}
