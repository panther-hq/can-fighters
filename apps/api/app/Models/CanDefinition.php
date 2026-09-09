<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'name', 'icon', 'rarity', 'drops'])]
class CanDefinition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drops' => 'array',
        ];
    }

    /**
     * How many ingredients this can yields per opening.
     */
    public function rolls(): int
    {
        return (int) ($this->drops['rolls'] ?? 1);
    }

    /**
     * @return array<string, int> ingredient slug => weight
     */
    public function weights(): array
    {
        return $this->drops['weights'] ?? [];
    }
}
