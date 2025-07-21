<?php

namespace App\Exceptions;

/**
 * Exception thrown when a requested task cannot be found
 */
class TaskNotFoundException extends DomainException
{
    public function __construct(int $taskId = null)
    {
        $message = $taskId 
            ? "Task with ID '{$taskId}' not found"
            : 'Task not found';
            
        parent::__construct($message);
    }

    public function getContext(): array
    {
        return [
            'type' => 'task_access_error',
            'reason' => 'not_found',
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 404; // Not Found
    }
}