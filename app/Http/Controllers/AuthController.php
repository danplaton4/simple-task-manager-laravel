<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\LoggingService;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Create the user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'], // Will be hashed automatically due to cast
                'preferred_language' => $validated['preferred_language'],
                'timezone' => $validated['timezone'],
            ]);

            // Create a token for the user
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

            LoggingService::logAuthEvent('user_registered', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Registration successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'preferred_language' => $user->preferred_language,
                    'timezone' => $user->timezone,
                    'created_at' => $user->created_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(30)->toISOString(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Registration failed. Please try again.',
                    'code' => 'REGISTRATION_FAILED',
                ]
            ], 500);
        }
    }

    /**
     * Authenticate user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Find user by email
            $user = User::where('email', $validated['email'])->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                LoggingService::logSecurityEvent('login_failed_invalid_credentials', [
                    'email' => $validated['email'],
                ]);

                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Check if user account is soft deleted
            if ($user->trashed()) {
                LoggingService::logSecurityEvent('login_failed_account_deactivated', [
                    'email' => $validated['email'],
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'error' => [
                        'message' => 'Your account has been deactivated. Please contact support.',
                        'code' => 'ACCOUNT_DEACTIVATED',
                    ]
                ], 403);
            }

            // Revoke existing tokens if not remembering
            if (!($validated['remember'] ?? false)) {
                $user->tokens()->delete();
            }

            // Create token with appropriate expiration
            $expiresAt = ($validated['remember'] ?? false) 
                ? now()->addDays(30) 
                : now()->addHours(24);

            $token = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;

            LoggingService::logAuthEvent('user_logged_in', [
                'user_id' => $user->id,
                'email' => $user->email,
                'remember' => $validated['remember'] ?? false,
            ]);

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'preferred_language' => $user->preferred_language,
                    'timezone' => $user->timezone,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'message' => 'Invalid credentials provided.',
                    'code' => 'INVALID_CREDENTIALS',
                    'details' => $e->errors(),
                ]
            ], 422);

        } catch (\Exception $e) {
            Log::error('Login failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Login failed. Please try again.',
                    'code' => 'LOGIN_FAILED',
                ]
            ], 500);
        }
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke the current token
            $request->user()->currentAccessToken()->delete();

            LoggingService::logAuthEvent('user_logged_out', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Successfully logged out',
            ]);

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Logout failed. Please try again.',
                    'code' => 'LOGOUT_FAILED',
                ]
            ], 500);
        }
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke all tokens for the user
            $user->tokens()->delete();

            LoggingService::logAuthEvent('user_logged_out_all_devices', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Successfully logged out from all devices',
            ]);

        } catch (\Exception $e) {
            Log::error('Logout all failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Logout failed. Please try again.',
                    'code' => 'LOGOUT_ALL_FAILED',
                ]
            ], 500);
        }
    }

    /**
     * Refresh the user's token.
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            // Create a new token with the same expiration time as the current one
            $expiresAt = $currentToken->expires_at ?? now()->addHours(24);
            $newToken = $user->createToken('auth-token', ['*'], $expiresAt)->plainTextToken;

            // Revoke the current token
            $currentToken->delete();

            LoggingService::logAuthEvent('token_refreshed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Token refreshed successfully',
                'token' => $newToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Token refresh failed. Please login again.',
                    'code' => 'TOKEN_REFRESH_FAILED',
                ]
            ], 500);
        }
    }

    /**
     * Get the authenticated user's information.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'preferred_language' => $user->preferred_language,
                    'timezone' => $user->timezone,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'token_info' => [
                    'name' => $currentToken->name,
                    'abilities' => $currentToken->abilities,
                    'expires_at' => $currentToken->expires_at?->toISOString(),
                    'last_used_at' => $currentToken->last_used_at?->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user info', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Failed to retrieve user information.',
                    'code' => 'USER_INFO_FAILED',
                ]
            ], 500);
        }
    }
}