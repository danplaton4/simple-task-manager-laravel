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
        // Enable stateful authentication for SPA (as per Laravel Sanctum documentation)
        // This middleware ensures that requests from stateful domains use session-based auth
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Add localization middleware only to web group (not API)
        $middleware->web(append: [
            \App\Http\Middleware\LocalizationMiddleware::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\LogApiRequests::class,
        ]);
        
        $middleware->alias([
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
            'auth.rate_limit' => \App\Http\Middleware\RateLimitAuth::class,
            'task.ownership' => \App\Http\Middleware\TaskOwnership::class,
            'localization' => \App\Http\Middleware\LocalizationMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            // Log all exceptions with structured data
            \App\Services\LoggingService::logError($e, [
                'request_method' => $request->method(),
                'request_path' => $request->path(),
                'request_id' => $request->header('X-Request-ID') ?? uniqid(),
            ]);

            if ($e instanceof \App\Exceptions\DomainException) {
                // Log domain exceptions as errors
                \Illuminate\Support\Facades\Log::error('DomainException', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'context' => $e->getContext(),
                    'code' => $e->getErrorCode(),
                    'request_path' => $request->path(),
                ]);
                return response()->json([
                    'error' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getErrorCode(),
                        'context' => $e->getContext(),
                    ]
                ], $e->getHttpStatusCode());
            }

            // Log security-related exceptions
            if ($e instanceof \Illuminate\Auth\AuthenticationException ||
                $e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                \App\Services\LoggingService::logSecurityEvent(
                    'exception_' . class_basename($e),
                    [
                        'message' => $e->getMessage(),
                        'request_path' => $request->path(),
                    ]
                );
            }

            return null; // Let Laravel handle the response
        });

        $exceptions->report(function (Throwable $e) {
            // Additional reporting logic can be added here
            // For example, sending to external monitoring services
        });
    })->create();
