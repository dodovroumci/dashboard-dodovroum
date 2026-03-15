<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ForceHttps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => Admin::class,
            'owner' => \App\Http\Middleware\OwnerMiddleware::class,
        ]);

        // Forcer HTTPS en production (le middleware vérifie lui-même l'environnement)
        $middleware->web(prepend: [
            ForceHttps::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
