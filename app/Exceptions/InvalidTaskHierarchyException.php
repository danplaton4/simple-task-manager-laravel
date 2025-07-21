<?php

namespace App\Exceptions;

/**
 * Exception thrown when a task hierarchy is invalid (e.g., circular reference, subtask of subtask)
 */
class InvalidTaskHierarchyException extends DomainException
{
    public function __construct(string $message = 'Invalid task hierarchy')
    {
        parent::__construct($message);
    }

    public function getContext(): array
    {
        return [
            'type' => 'task_hierarchy_error',
            'reason' => 'invalid_hierarchy',
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }
} 