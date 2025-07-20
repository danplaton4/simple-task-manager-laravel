<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\LoggingService;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        // Log the API request
        LoggingService::logApiRequest($request, $response, $duration);

        // Log performance if request took longer than threshold
        if ($duration > config('logging.performance_threshold', 1.0)) {
            LoggingService::logPerformance(
                'slow_api_request',
                $duration,
                [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                ]
            );
        }

        return $response;
    }
}