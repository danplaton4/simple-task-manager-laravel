<?php

namespace App\Http\Controllers;

use App\Services\LocaleCacheService;
use App\Services\TranslationPerformanceMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LocaleController extends ApiController
{
    /**
     * Supported locales
     */
    protected array $supportedLocales = ['en', 'de', 'fr'];

    /**
     * Locale cache service
     */
    protected LocaleCacheService $cacheService;

    /**
     * Translation performance monitor
     */
    protected TranslationPerformanceMonitor $performanceMonitor;

    public function __construct(
        LocaleCacheService $cacheService,
        TranslationPerformanceMonitor $performanceMonitor
    ) {
        $this->cacheService = $cacheService;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Update user's locale preference
     */
    public function updatePreference(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'locale' => ['required', 'string', Rule::in($this->supportedLocales)],
            ]);

            $locale = $request->input('locale');
            $user = $request->user();

            // Update user's preferred language
            $user->update(['preferred_language' => $locale]);

            // Cache the user's locale preference
            $this->cacheService->cacheUserLocale($user->id, $locale);

            // Invalidate user's cached data to force refresh with new locale
            $this->cacheService->invalidateUserCache($user->id);

            return $this->success([
                'locale' => $locale,
                'message' => 'Language preference updated successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(
                'Invalid locale provided',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $e->errors()
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to update language preference',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get current locale information
     */
    public function getCurrentLocale(): JsonResponse
    {
        try {
            $user = Auth::user();
            $userPreference = null;

            if ($user) {
                // Try to get from cache first
                $userPreference = $this->cacheService->getCachedUserLocale($user->id);
                
                // If not in cache, get from database and cache it
                if ($userPreference === null) {
                    $userPreference = $user->preferred_language;
                    if ($userPreference) {
                        $this->cacheService->cacheUserLocale($user->id, $userPreference);
                    }
                }
            }

            return $this->success([
                'locale' => App::getLocale(),
                'user_preference' => $userPreference,
                'available_locales' => array_combine(
                    $this->supportedLocales,
                    array_map(fn($locale) => ucfirst($locale), $this->supportedLocales)
                ),
                'cache_metrics' => $this->cacheService->getCacheMetrics()
            ]);
        } catch (\Exception $e) {
            return $this->error(
                'Failed to retrieve locale information',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get current locale information (legacy endpoint)
     */
    public function current(): JsonResponse
    {
        return response()->json([
            'current_locale' => App::getLocale(),
            'supported_locales' => $this->supportedLocales,
            'user_preferred_locale' => Auth::check() ? Auth::user()->preferred_language : null,
        ]);
    }

    /**
     * Switch application locale (legacy endpoint)
     */
    public function switch(Request $request): JsonResponse
    {
        $request->validate([
            'locale' => ['required', 'string', Rule::in($this->supportedLocales)],
        ]);

        $locale = $request->input('locale');

        // Set locale for current request
        App::setLocale($locale);

        // Store in session for future requests
        $request->session()->put('locale', $locale);

        // Update user's preferred language if authenticated
        if (Auth::check()) {
            Auth::user()->update(['preferred_language' => $locale]);
        }

        return response()->json([
            'message' => __('messages.general.success'),
            'locale' => $locale,
        ]);
    }

    /**
     * Get available translations for a specific key
     */
    public function translations(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $key = $request->input('key');
        $translations = [];

        foreach ($this->supportedLocales as $locale) {
            App::setLocale($locale);
            $translations[$locale] = __($key);
        }

        // Reset to original locale
        App::setLocale($request->header('Accept-Language', config('app.locale')));

        return response()->json([
            'key' => $key,
            'translations' => $translations,
        ]);
    }

    /**
     * Get translation performance metrics
     */
    public function performanceMetrics(): JsonResponse
    {
        try {
            $metrics = $this->performanceMonitor->getMetrics();
            $cacheStats = $this->performanceMonitor->getCacheStats();
            $dbStats = $this->performanceMonitor->getDatabaseStats();

            return $this->success([
                'performance_metrics' => $metrics,
                'cache_stats' => $cacheStats,
                'database_stats' => $dbStats,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return $this->error(
                'Failed to retrieve performance metrics',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Clear translation performance metrics
     */
    public function clearMetrics(): JsonResponse
    {
        try {
            $this->performanceMonitor->clearMetrics();
            
            return $this->success([
                'message' => 'Performance metrics cleared successfully',
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return $this->error(
                'Failed to clear performance metrics',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
