<?php

namespace App\Http\Controllers;

use App\DTOs\Auth\LoginUserDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function __construct(private AuthService $authService) {}

    /**
     * Register a new user for SPA.
     * For SPAs, we use session-based authentication, not tokens.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create user manually for SPA authentication
            $user = \App\Models\User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'preferred_language' => $request->preferred_language ?? 'en',
                'timezone' => $request->timezone ?? 'UTC',
            ]);
            
            // Log the user in immediately
            auth()->login($user);
            
            return $this->success([
                'user' => $user->toArray(),
                'message' => 'Registration successful'
            ], 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Authenticate user for SPA.
     * For SPAs, we use session-based authentication, not tokens.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Validate credentials manually for SPA authentication
            $credentials = $request->only('email', 'password');
            
            if (!auth()->attempt($credentials, $request->boolean('remember'))) {
                return $this->error('The provided credentials are incorrect.', 422, [
                    'email' => ['The provided credentials are incorrect.']
                ]);
            }
            
            $user = auth()->user();
            
            return $this->success([
                'user' => $user->toArray(),
                'message' => 'Login successful'
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Logout user from SPA session.
     */
    public function logout(Request $request): JsonResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return $this->success(['message' => 'Logout successful'], 200);
    }

    /**
     * Logout from all devices (for SPA, this is the same as regular logout).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return $this->success(['message' => 'Logout successful'], 200);
    }

    /**
     * Refresh the user's token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $tokenId = $request->user()->currentAccessToken()->id;
        $result = $this->authService->refreshToken($request->user(), $tokenId);
        if ($result) {
            return $this->success($result->toApiResponse());
        }
        return $this->error('Token refresh failed', 400);
    }

    /**
     * Get the authenticated user's information for SPA.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'user' => $user->toArray(),
        ]);
    }
}