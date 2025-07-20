<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class UnsubscribeController extends Controller
{
    /**
     * Show unsubscribe page
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $token = $request->query('token');
            
            if (!$token) {
                return response()->json([
                    'error' => 'Invalid unsubscribe link',
                    'message' => 'The unsubscribe token is missing.'
                ], 400);
            }

            $userId = $this->decryptToken($token);
            
            if (!$userId) {
                return response()->json([
                    'error' => 'Invalid unsubscribe link',
                    'message' => 'The unsubscribe token is invalid or expired.'
                ], 400);
            }

            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'The user associated with this unsubscribe link could not be found.'
                ], 404);
            }

            return response()->json([
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'current_preferences' => $user->getNotificationPreferences(),
                'token' => $token
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to show unsubscribe page', [
                'error' => $e->getMessage(),
                'token' => $request->query('token')
            ]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
    }

    /**
     * Process unsubscribe request
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'preferences' => 'required|array',
                'preferences.email_notifications' => 'boolean',
                'preferences.task_created' => 'boolean',
                'preferences.task_updated' => 'boolean',
                'preferences.task_completed' => 'boolean',
                'preferences.task_deleted' => 'boolean',
                'preferences.task_due_soon' => 'boolean',
                'preferences.task_overdue' => 'boolean',
                'preferences.daily_digest' => 'boolean',
                'preferences.weekly_digest' => 'boolean',
            ]);

            $userId = $this->decryptToken($request->token);
            
            if (!$userId) {
                return response()->json([
                    'error' => 'Invalid unsubscribe link',
                    'message' => 'The unsubscribe token is invalid or expired.'
                ], 400);
            }

            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'The user associated with this unsubscribe link could not be found.'
                ], 404);
            }

            // Update notification preferences
            $user->updateNotificationPreferences($request->preferences);

            Log::info('User updated notification preferences via unsubscribe', [
                'user_id' => $user->id,
                'preferences' => $request->preferences
            ]);

            return response()->json([
                'message' => 'Your notification preferences have been updated successfully.',
                'updated_preferences' => $user->getNotificationPreferences()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process unsubscribe request', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'An error occurred while updating your preferences.'
            ], 500);
        }
    }

    /**
     * Unsubscribe from all notifications
     */
    public function unsubscribeAll(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string',
            ]);

            $userId = $this->decryptToken($request->token);
            
            if (!$userId) {
                return response()->json([
                    'error' => 'Invalid unsubscribe link',
                    'message' => 'The unsubscribe token is invalid or expired.'
                ], 400);
            }

            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                    'message' => 'The user associated with this unsubscribe link could not be found.'
                ], 404);
            }

            // Disable all notifications
            $user->disableAllNotifications();

            Log::info('User unsubscribed from all notifications', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'You have been successfully unsubscribed from all email notifications.',
                'updated_preferences' => $user->getNotificationPreferences()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe user from all notifications', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Server error',
                'message' => 'An error occurred while unsubscribing you from notifications.'
            ], 500);
        }
    }

    /**
     * Generate unsubscribe token for a user
     */
    public static function generateUnsubscribeToken(int $userId): string
    {
        $data = [
            'user_id' => $userId,
            'expires_at' => now()->addMonths(6)->timestamp
        ];

        return Crypt::encryptString(json_encode($data));
    }

    /**
     * Decrypt and validate unsubscribe token
     */
    private function decryptToken(string $token): ?int
    {
        try {
            $decrypted = Crypt::decryptString($token);
            $data = json_decode($decrypted, true);

            if (!$data || !isset($data['user_id']) || !isset($data['expires_at'])) {
                return null;
            }

            // Check if token is expired
            if ($data['expires_at'] < now()->timestamp) {
                return null;
            }

            return $data['user_id'];

        } catch (\Exception $e) {
            Log::warning('Failed to decrypt unsubscribe token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
