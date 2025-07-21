<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Base service class providing common functionality
 */
abstract class BaseService
{
    /**
     * Execute a database transaction with automatic rollback on failure
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Log an event with context
     */
    protected function logEvent(string $level, string $message, array $context = []): void
    {
        Log::log($level, $message, array_merge([
            'service' => static::class,
            'timestamp' => now()->toISOString(),
        ], $context));
    }

    /**
     * Log an info event
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logEvent('info', $message, $context);
    }

    /**
     * Log an error event
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logEvent('error', $message, $context);
    }

    /**
     * Log a warning event
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->logEvent('warning', $message, $context);
    }

    /**
     * Validate required parameters
     */
    protected function validateRequired(array $data, array $required): void
    {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required fields: ' . implode(', ', $missing)
            );
        }
    }
}