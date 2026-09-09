<?php

namespace App\Providers;

use App\Domain\Mixer\CharacterGenerationProvider;
use App\Domain\Mixer\FallbackCharacterGenerationProvider;
use App\Domain\Mixer\MockCharacterGenerationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The mock is a singleton so tests can resolve it, configure `$next`
        // / `$shouldThrow`, and have the service pick up the same instance.
        $this->app->singleton(MockCharacterGenerationProvider::class);

        $this->app->bind(CharacterGenerationProvider::class, function ($app) {
            return match (config('mixer.provider')) {
                'mock' => $app->make(MockCharacterGenerationProvider::class),
                default => $app->make(FallbackCharacterGenerationProvider::class),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
