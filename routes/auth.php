<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Here are the authentication routes for the API. These routes handle
| user registration, login, logout, and token refresh functionality
| using Laravel Sanctum for SPA authentication.
|
*/

// CSRF Cookie route for SPA authentication
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Alternative CSRF cookie route
Route::get('/csrf-cookie', function (Request $request) {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Public authentication routes with rate limiting
Route::prefix('auth')->middleware('auth.rate_limit')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    
    Route::post('/forgot-password', function (Request $request) {
        // Placeholder for password reset logic - to be implemented in future task
        return response()->json(['message' => 'Password reset endpoint - to be implemented']);
    })->name('auth.forgot-password');
});

// Protected authentication routes (require authentication)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
});