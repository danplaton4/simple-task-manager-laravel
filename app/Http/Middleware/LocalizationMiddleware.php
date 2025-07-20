<?php

namespace App\Http\Middleware;

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
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request);
        
        if (in_array($locale, $this->supportedLocales)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Detect the appropriate locale for the request
     */
    protected function detectLocale(Request $request): string
    {
        // 1. Check for explicit locale parameter in request
        if ($request->has('locale') && in_array($request->get('locale'), $this->supportedLocales)) {
            return $request->get('locale');
        }

        // 2. Check for locale in headers (for API requests)
        if ($request->hasHeader('Accept-Language')) {
            $headerLocale = $this->parseAcceptLanguageHeader($request->header('Accept-Language'));
            if ($headerLocale && in_array($headerLocale, $this->supportedLocales)) {
                return $headerLocale;
            }
        }

        // 3. Check authenticated user's preferred language
        if (Auth::check() && Auth::user()->preferred_language) {
            $userLocale = Auth::user()->preferred_language;
            if (in_array($userLocale, $this->supportedLocales)) {
                return $userLocale;
            }
        }

        // 4. Check session for stored locale
        if ($request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, $this->supportedLocales)) {
                return $sessionLocale;
            }
        }

        // 5. Fall back to application default
        return config('app.locale', 'en');
    }

    /**
     * Parse Accept-Language header to extract preferred locale
     */
    protected function parseAcceptLanguageHeader(string $acceptLanguage): ?string
    {
        $languages = explode(',', $acceptLanguage);
        
        foreach ($languages as $language) {
            $locale = trim(explode(';', $language)[0]);
            $locale = strtolower(substr($locale, 0, 2)); // Get first 2 characters
            
            if (in_array($locale, $this->supportedLocales)) {
                return $locale;
            }
        }

        return null;
    }
}
