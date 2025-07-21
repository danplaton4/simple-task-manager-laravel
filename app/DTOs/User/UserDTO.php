<?php

namespace App\DTOs\User;

use App\DTOs\BaseDTO;
use App\Models\User;
use Carbon\Carbon;

class UserDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $preferredLanguage,
        public readonly string $timezone,
        public readonly Carbon $createdAt,
        public readonly ?Carbon $updatedAt = null
    ) {}

    /**
     * Create DTO from User model.
     */
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            preferredLanguage: $user->preferred_language,
            timezone: $user->timezone,
            createdAt: $user->created_at,
            updatedAt: $user->updated_at
        );
    }

    /**
     * Convert to array with snake_case keys for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'preferred_language' => $this->preferredLanguage,
            'timezone' => $this->timezone,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}