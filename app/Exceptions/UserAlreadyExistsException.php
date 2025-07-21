<?php

namespace App\Exceptions;

/**
 * Exception thrown when attempting to create a user that already exists
 */
class UserAlreadyExistsException extends DomainException
{
    public function __construct(string $email = '')
    {
        $message = $email 
            ? "User with email '{$email}' already exists"
            : 'User already exists';
            
        parent::__construct($message);
    }

    public function getContext(): array
    {
        return [
            'type' => 'user_registration_error',
            'reason' => 'duplicate_email',
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 409; // Conflict
    }
}