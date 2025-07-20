<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Include authentication routes
require __DIR__.'/auth.php';

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'database' => 'connected', // Could add actual DB check here
        'cache' => 'connected', // Could add actual cache check here
    ]);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'preferences' => [
                'language' => $request->user()->preferred_language ?? 'en',
                'timezone' => $request->user()->timezone ?? 'UTC',
            ]
        ]);
    });
    
    // Task management routes
    Route::apiResource('tasks', App\Http\Controllers\TaskController::class);
    Route::post('/tasks/{id}/restore', [App\Http\Controllers\TaskController::class, 'restore']);
    
    // Subtask management routes
    Route::get('/tasks/{id}/subtasks', [App\Http\Controllers\TaskController::class, 'subtasks']);
    Route::post('/tasks/{parentId}/subtasks', [App\Http\Controllers\TaskController::class, 'createSubtask']);
    Route::put('/tasks/{parentId}/subtasks/reorder', [App\Http\Controllers\TaskController::class, 'reorderSubtasks']);
    Route::put('/subtasks/{subtaskId}/move', [App\Http\Controllers\TaskController::class, 'moveSubtask']);
    Route::post('/tasks/{parentId}/subtasks/bulk', [App\Http\Controllers\TaskController::class, 'bulkSubtaskOperations']);
});