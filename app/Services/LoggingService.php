<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LoggingService
{
    /**
     * Log API request with structured data
     */
    public static function logApiRequest(Request $request, $response = null, $duration = null): void
    {
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'request_id' => $request->header('X-Request-ID') ?? uniqid(),
            'headers' => self::sanitizeHeaders($request->headers->all()),
            'query_params' => $request->query(),
            'request_size' => strlen($request->getContent()),
        ];

        if ($response) {
            $logData['response_status'] = $response->getStatusCode();
            $logData['response_size'] = strlen($response->getContent());
        }

        if ($duration !== null) {
            $logData['duration_ms'] = round($duration * 1000, 2);
        }

        Log::channel('api')->info('API Request', $logData);
    }

    /**
     * Log authentication events
     */
    public static function logAuthEvent(string $event, array $context = []): void
    {
        $logData = array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => Auth::id(),
        ], $context);

        Log::channel('auth')->info("Auth Event: {$event}", $logData);
    }

    /**
     * Log task operations
     */
    public static function logTaskOperation(string $operation, array $context = []): void
    {
        $logData = array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'operation' => $operation,
            'user_id' => Auth::id(),
            'ip' => request()->ip(),
        ], $context);

        Log::channel('tasks')->info("Task Operation: {$operation}", $logData);
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $logData = array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => Auth::id(),
            'severity' => 'high',
        ], $context);

        Log::channel('security')->warning("Security Event: {$event}", $logData);
    }

    /**
     * Log performance metrics
     */
    public static function logPerformance(string $operation, float $duration, array $context = []): void
    {
        $logData = array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ], $context);

        Log::channel('performance')->info("Performance: {$operation}", $logData);
    }

    /**
     * Log queue job events
     */
    public static function logQueueJob(string $event, string $jobClass, array $context = []): void
    {
        $logData = array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'event' => $event,
            'job_class' => $jobClass,
            'queue' => config('queue.default'),
        ], $context);

        Log::channel('queue')->info("Queue Job: {$event}", $logData);
    }

    /**
     * Log application errors with context
     */
    public static function logError(\Throwable $exception, array $context = []): void
    {
        $logData = array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => Auth::id(),
            'ip' => request()->ip(),
            'url' => request()->fullUrl(),
        ], $context);

        Log::error('Application Error', $logData);
    }

    /**
     * Sanitize headers to remove sensitive information
     */
    private static function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }
}