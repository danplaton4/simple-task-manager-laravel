<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Supported locales
     */
    protected array $supportedLocales = ['en', 'de', 'fr'];

    /**
     * Get current locale information
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
     * Switch application locale
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
}
