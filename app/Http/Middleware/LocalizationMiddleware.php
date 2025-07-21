<?php

namespace App\Http\Middleware;

use App\Services\LocaleCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    /**
     * Supported locales
     */
    protected array $supportedLocales = ['en', 'de', 'fr'];

    /**
     * Locale cache service
     */
    protected LocaleCacheService $cacheService;

    public function __construct(LocaleCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request);
        
        if (in_array($locale, $this->supportedLocales)) {
            App::setLocale($locale);
            
            // Store detected locale in request attributes for later use
            $request->attributes->set('locale', $locale);
        }

        return $next($request);
    }

    /**
     * Detect the appropriate locale for the request
     */
    protected function detectLocale(Request $request): string
    {
        // 1. Check X-Locale header first (from frontend)
        if ($request->hasHeader('X-Locale')) {
            $headerLocale = $request->header('X-Locale');
            if (in_array($headerLocale, $this->supportedLocales)) {
                return $headerLocale;
            }
        }

        // 2. Check authenticated user's preferred language (with caching)
        if (Auth::check()) {
            $user = Auth::user();
            
            // Try to get from cache first
            $userLocale = $this->cacheService->getCachedUserLocale($user->id);
            
            // If not in cache, get from database and cache it
            if ($userLocale === null && $user->preferred_language) {
                $userLocale = $user->preferred_language;
                $this->cacheService->cacheUserLocale($user->id, $userLocale);
            }
            
            if ($userLocale && in_array($userLocale, $this->supportedLocales)) {
                return $userLocale;
            }
        }

        // 3. Check for explicit locale parameter in request
        if ($request->has('locale') && in_array($request->get('locale'), $this->supportedLocales)) {
            return $request->get('locale');
        }

        // 4. Check Accept-Language header with improved parsing
        if ($request->hasHeader('Accept-Language')) {
            $headerLocale = $this->parseAcceptLanguageHeader($request->header('Accept-Language'));
            if ($headerLocale && in_array($headerLocale, $this->supportedLocales)) {
                return $headerLocale;
            }
        }

        // 5. Check session for stored locale (only if session is available)
        if ($request->hasSession() && $request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, $this->supportedLocales)) {
                return $sessionLocale;
            }
        }

        // 6. Fall back to application default
        return config('app.locale', 'en');
    }

    /**
     * Parse Accept-Language header to extract preferred locale with improved logic
     */
    protected function parseAcceptLanguageHeader(string $acceptLanguage): ?string
    {
        // Parse the Accept-Language header and sort by quality values
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, ';q=') !== false) {
                [$locale, $quality] = explode(';q=', $part, 2);
                $quality = (float) $quality;
            } else {
                $locale = $part;
                $quality = 1.0; // Default quality
            }
            
            // Extract language code (first 2 characters)
            $locale = strtolower(substr(trim($locale), 0, 2));
            
            if (in_array($locale, $this->supportedLocales)) {
                $languages[] = ['locale' => $locale, 'quality' => $quality];
            }
        }
        
        // Sort by quality (highest first)
        usort($languages, function ($a, $b) {
            return $b['quality'] <=> $a['quality'];
        });
        
        // Return the highest quality supported locale
        return !empty($languages) ? $languages[0]['locale'] : null;
    }
}
