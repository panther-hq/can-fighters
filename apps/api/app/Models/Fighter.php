<?php

namespace App\Models;

use Database\Factories\FighterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'name', 'description', 'primary_class', 'secondary_class', 'rarity',
    'personality', 'level', 'xp', 'traits', 'visual_dna', 'suggested_skills',
    'generation_seed', 'generation_version',
])]
class Fighter extends Model
{
    /** @use HasFactory<FighterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'traits' => 'array',
            'visual_dna' => 'array',
            'suggested_skills' => 'array',
            'generation_seed' => 'integer',
            'generation_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
