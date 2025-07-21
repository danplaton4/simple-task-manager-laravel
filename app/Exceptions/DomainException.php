<?php

namespace App\Exceptions;

use Exception;

/**
 * Base domain exception for business logic errors
 */
abstract class DomainException extends Exception
{
    /**
     * Get the error code for this exception
     */
    public function getErrorCode(): string
    {
        return static::class;
    }

    /**
     * Get additional context for the exception
     */
    public function getContext(): array
    {
        return [];
    }

    /**
     * Get the HTTP status code for this exception
     */
    public function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity by default
    }
}