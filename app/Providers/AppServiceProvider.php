<?php

namespace App\Providers;

use App\Auth\ApiUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enregistrer le provider personnalisé pour l'authentification API
        Auth::provider('api', function ($app, array $config) {
            return new ApiUserProvider();
        });
    }
}
