<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Add localization middleware to both web and api groups
        $middleware->web(append: [
            \App\Http\Middleware\LocalizationMiddleware::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\LocalizationMiddleware::class,
        ]);
        
        $middleware->alias([
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
            'auth.rate_limit' => \App\Http\Middleware\RateLimitAuth::class,
            'task.ownership' => \App\Http\Middleware\TaskOwnership::class,
            'localization' => \App\Http\Middleware\LocalizationMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
