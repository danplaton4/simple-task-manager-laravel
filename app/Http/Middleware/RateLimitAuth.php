<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        // Different limits for different endpoints
        $maxAttempts = match ($request->route()->getName()) {
            'auth.login' => 5, // 5 login attempts per minute
            'auth.register' => 3, // 3 registration attempts per minute
            'auth.forgot-password' => 2, // 2 password reset attempts per minute
            default => 10, // Default rate limit
        };
        
        $decayMinutes = 1; // Reset every minute
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'error' => [
                    'message' => 'Too many attempts. Please try again later.',
                    'retry_after' => $seconds,
                ]
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        // Clear rate limit on successful authentication
        if ($response->getStatusCode() === 200 && $request->route()->getName() === 'auth.login') {
            RateLimiter::clear($key);
        }
        
        return $response;
    }
    
    /**
     * Resolve the rate limiting signature for the request.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->route()->getName() . '|' . 
            $request->ip() . '|' . 
            ($request->input('email') ?? '')
        );
    }
}
