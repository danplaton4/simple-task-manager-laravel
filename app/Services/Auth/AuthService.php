<?php

namespace App\Services\Auth;

use App\DTOs\Auth\AuthResultDTO;
use App\DTOs\Auth\LoginUserDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Exceptions\UserAlreadyExistsException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\BaseService;
use App\Services\LoggingService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService extends BaseService
{
    /**
     * Create a new AuthService instance.
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoggingService $loggingService
    ) {}

    /**
     * Register a new user.
     *
     * @param RegisterUserDTO $dto
     * @return AuthResultDTO
     * @throws UserAlreadyExistsException
     */
    public function registerUser(RegisterUserDTO $dto): AuthResultDTO
    {
        // Check if user exists
        if ($this->userRepository->existsByEmail($dto->email)) {
            $this->loggingService->logSecurityEvent('registration_failed_email_exists', [
                'email' => $dto->email,
            ]);
            throw new UserAlreadyExistsException('A user with this email already exists.');
        }

        // Create user through repository
        $user = $this->userRepository->createFromDTO($dto);
        
        // Generate token
        $expiresAt = now()->addDays(30);
        $token = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;
        
        // Log the event
        $this->loggingService->logAuthEvent('user_registered', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return AuthResultDTO::registrationSuccess($user, $token, $expiresAt);
    }

    /**
     * Authenticate a user.
     *
     * @param LoginUserDTO $dto
     * @return AuthResultDTO
     * @throws ValidationException
     */
    public function loginUser(LoginUserDTO $dto): AuthResultDTO
    {
        // Find user by email
        $user = $this->userRepository->findByEmail($dto->email);

        // Check if user exists and password is correct
        if (!$user || !Hash::check($dto->password, $user->password)) {
            $this->loggingService->logSecurityEvent('login_failed_invalid_credentials', [
                'email' => $dto->email,
            ]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user account is soft deleted
        if ($user->trashed()) {
            $this->loggingService->logSecurityEvent('login_failed_account_deactivated', [
                'email' => $dto->email,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Revoke existing tokens if not remembering
        if (!$dto->remember) {
            $user->tokens()->delete();
        }

        // Create token with appropriate expiration
        $expiresAt = $dto->getTokenExpiration();
        $token = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;

        $this->loggingService->logAuthEvent('user_logged_in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'remember' => $dto->remember,
        ]);

        return AuthResultDTO::loginSuccess($user, $token, $expiresAt);
    }

    /**
     * Logout a user (revoke current token).
     *
     * @param User $user
     * @param string $tokenId
     * @return bool
     */
    public function logoutUser(User $user, string $tokenId): bool
    {
        try {
            // Find and revoke the specific token
            $token = $user->tokens()->where('id', $tokenId)->first();
            
            if ($token) {
                $token->delete();
            }
            
            $this->loggingService->logAuthEvent('user_logged_out', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->loggingService->logError($e, [
                'action' => 'logout',
                'user_id' => $user->id,
            ]);
            
            return false;
        }
    }

    /**
     * Logout a user from all devices (revoke all tokens).
     *
     * @param User $user
     * @return bool
     */
    public function logoutAllDevices(User $user): bool
    {
        try {
            // Revoke all tokens for the user
            $user->tokens()->delete();
            
            $this->loggingService->logAuthEvent('user_logged_out_all_devices', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->loggingService->logError($e, [
                'action' => 'logout_all',
                'user_id' => $user->id,
            ]);
            
            return false;
        }
    }

    /**
     * Refresh a user's token.
     *
     * @param User $user
     * @param string $tokenId
     * @return AuthResultDTO|null
     */
    public function refreshToken(User $user, string $tokenId): ?AuthResultDTO
    {
        try {
            // Find the current token
            $currentToken = $user->tokens()->where('id', $tokenId)->first();
            
            if (!$currentToken) {
                return null;
            }

            // Create a new token with the same expiration time as the current one
            $expiresAt = $currentToken->expires_at ?? now()->addHours(24);
            $newToken = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;

            // Revoke the current token
            $currentToken->delete();

            $this->loggingService->logAuthEvent('token_refreshed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return AuthResultDTO::fromUserAndToken(
                $user,
                $newToken,
                $expiresAt,
                'Token refreshed successfully'
            );
        } catch (\Exception $e) {
            $this->loggingService->logError($e, [
                'action' => 'refresh_token',
                'user_id' => $user->id,
            ]);
            
            return null;
        }
    }
}