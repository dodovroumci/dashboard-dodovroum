<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Enregistrement direct sans import complexe
        Auth::provider('plain-text', function ($app, array $config) {
            return new \App\Extensions\PlainTextUserProvider($app['hash'], $config['model']);
        });
    }
}
