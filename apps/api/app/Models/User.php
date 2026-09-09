<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasOne<PlayerProfile, $this>
     */
    public function playerProfile(): HasOne
    {
        return $this->hasOne(PlayerProfile::class);
    }

    /**
     * @return HasMany<PlayerIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(PlayerIngredient::class);
    }

    /**
     * @return HasMany<PlayerCan, $this>
     */
    public function cans(): HasMany
    {
        return $this->hasMany(PlayerCan::class);
    }

    /**
     * @return HasMany<CanOpening, $this>
     */
    public function canOpenings(): HasMany
    {
        return $this->hasMany(CanOpening::class);
    }
}
