<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Hash;

class RegisterUserDTO extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $preferredLanguage,
        public readonly string $timezone
    ) {}

    /**
     * Create DTO from RegisterRequest.
     */
    public static function fromRequest(RegisterRequest $request): self
    {
        $validated = $request->validated();
        
        return new self(
            name: trim($validated['name']),
            email: strtolower(trim($validated['email'])),
            password: $validated['password'], // Will be hashed by the service/repository
            preferredLanguage: $validated['preferred_language'] ?? 'en',
            timezone: $validated['timezone'] ?? 'UTC'
        );
    }

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            email: strtolower(trim($data['email'])),
            password: $data['password'],
            preferredLanguage: $data['preferred_language'] ?? 'en',
            timezone: $data['timezone'] ?? 'UTC'
        );
    }

    /**
     * Get the data ready for model creation.
     */
    public function toModelData(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password, // Will be auto-hashed by User model cast
            'preferred_language' => $this->preferredLanguage,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * Validate the DTO data.
     */
    public function validate(): array
    {
        $errors = [];

        if (empty(trim($this->name))) {
            $errors['name'] = 'Name is required';
        }

        if (empty(trim($this->email)) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        if (empty($this->password) || strlen($this->password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (!in_array($this->preferredLanguage, ['en', 'de', 'fr'])) {
            $errors['preferred_language'] = 'Invalid preferred language';
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
}