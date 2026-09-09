<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'generated_on', 'offers'])]
class PlayerShop extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_on' => 'date',
            'offers' => 'array',
        ];
    }
}
