<?php

namespace App\Providers;

use App\Contracts\TTSProvider;
use App\Services\TTS\ElevenLabsProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the TTS provider interface to the configured implementation.
        // To swap TTS providers, change the binding here — controllers stay untouched.
        $this->app->bind(TTSProvider::class, ElevenLabsProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
