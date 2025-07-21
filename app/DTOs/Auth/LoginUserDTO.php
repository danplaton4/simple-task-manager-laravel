<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;
use App\Http\Requests\LoginRequest;

class LoginUserDTO extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember = false
    ) {}

    /**
     * Create DTO from LoginRequest.
     */
    public static function fromRequest(LoginRequest $request): self
    {
        $validated = $request->validated();
        
        return new self(
            email: strtolower(trim($validated['email'])),
            password: $validated['password'],
            remember: $validated['remember'] ?? false
        );
    }

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim($data['email'])),
            password: $data['password'],
            remember: $data['remember'] ?? false
        );
    }

    /**
     * Validate the DTO data.
     */
    public function validate(): array
    {
        $errors = [];

        if (empty(trim($this->email)) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        }

        if (!is_bool($this->remember)) {
            $errors['remember'] = 'Remember must be a boolean value';
        }

        return $errors;
    }

    /**
     * Check if the DTO is valid.
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Get token expiration based on remember flag.
     */
    public function getTokenExpiration(): \Carbon\Carbon
    {
        return $this->remember 
            ? now()->addDays(30) 
            : now()->addHours(24);
    }
}