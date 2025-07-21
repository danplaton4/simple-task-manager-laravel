<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;
use App\DTOs\User\UserDTO;
use App\Models\User;
use Carbon\Carbon;

class AuthResultDTO extends BaseDTO
{
    public function __construct(
        public readonly UserDTO $user,
        public readonly string $token,
        public readonly string $tokenType = 'Bearer',
        public readonly ?Carbon $expiresAt = null,
        public readonly ?string $message = null
    ) {}

    /**
     * Create DTO from User model and token.
     */
    public static function fromUserAndToken(
        User $user, 
        string $token, 
        ?Carbon $expiresAt = null,
        ?string $message = null
    ): self {
        return new self(
            user: UserDTO::fromModel($user),
            token: $token,
            tokenType: 'Bearer',
            expiresAt: $expiresAt,
            message: $message
        );
    }

    /**
     * Create success result for registration.
     */
    public static function registrationSuccess(User $user, string $token, Carbon $expiresAt): self
    {
        return self::fromUserAndToken(
            user: $user,
            token: $token,
            expiresAt: $expiresAt,
            message: 'Registration successful'
        );
    }

    /**
     * Create success result for login.
     */
    public static function loginSuccess(User $user, string $token, Carbon $expiresAt): self
    {
        return self::fromUserAndToken(
            user: $user,
            token: $token,
            expiresAt: $expiresAt,
            message: 'Login successful'
        );
    }

    /**
     * Convert to API response format.
     */
    public function toApiResponse(): array
    {
        $response = [
            'user' => $this->user->toArray(),
            'token' => $this->token,
            'token_type' => $this->tokenType,
        ];

        if ($this->expiresAt) {
            $response['expires_at'] = $this->expiresAt->toISOString();
        }

        if ($this->message) {
            $response['message'] = $this->message;
        }

        return $response;
    }
}