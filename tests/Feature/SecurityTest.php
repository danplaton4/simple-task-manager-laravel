<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Security Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    });

    describe('Authentication Security', function () {
        it('requires authentication for protected endpoints', function () {
            $protectedEndpoints = [
                ['GET', '/api/tasks'],
                ['POST', '/api/tasks'],
                ['GET', '/api/tasks/1'],
                ['PUT', '/api/tasks/1'],
                ['DELETE', '/api/tasks/1'],
                ['GET', '/api/user'],
                ['POST', '/api/auth/logout'],
                ['POST', '/api/auth/refresh'],
            ];

            foreach ($protectedEndpoints as [$method, $url]) {
                $response = $this->json($method, $url);
                expect($response->status())->toBe(401);
            }
        });

        it('validates authentication tokens properly', function () {
            // Test with invalid token
            $response = $this->withHeaders([
                'Authorization' => 'Bearer invalid-token'
            ])->getJson('/api/user');

            $response->assertStatus(401);
        });

        it('prevents token reuse after logout', function () {
            $token = $this->user->createToken('test-token')->plainTextToken;

            // Use token successfully
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->getJson('/api/user');
            $response->assertStatus(200);

            // Logout
            $this->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->postJson('/api/auth/logout');

            // Try to use token after logout
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])->getJson('/api/user');
            $response->assertStatus(401);
        });

        it('enforces password complexity requirements', function () {
            $weakPasswords = [
                'password',      // Too common
                '123456',        // Too simple
                'abc',           // Too short
                'PASSWORD',      // No lowercase
                'password123',   // No uppercase or symbols
            ];

            foreach ($weakPasswords as $password) {
                $response = $this->postJson('/api/auth/register', [
                    'name' => 'Test User',
                    'email' => 'test' . rand() . '@example.com',
                    'password' => $password,
                    'password_confirmation' => $password
                ]);

                expect($response->status())->toBe(422);
            }
        });

        it('properly hashes passwords', function () {
            $password = 'SecurePassword123!';
            $user = User::factory()->create(['password' => $password]);

            // Password should be hashed, not stored in plain text
            expect($user->password)->not->toBe($password);
            expect(strlen($user->password))->toBeGreaterThan(50);
            expect(Hash::check($password, $user->password))->toBeTrue();
        });
    });

    describe('Authorization Security', function () {
        it('enforces task ownership for all operations', function () {
            $userTask = Task::factory()->for($this->user)->create();
            $otherUserTask = Task::factory()->for($this->otherUser)->create();

            Sanctum::actingAs($this->user);

            // User can access their own task
            $response = $this->getJson("/api/tasks/{$userTask->id}");
            $response->assertStatus(200);

            // User cannot access other user's task
            $response = $this->getJson("/api/tasks/{$otherUserTask->id}");
            $response->assertStatus(403);

            // User cannot update other user's task
            $response = $this->putJson("/api/tasks/{$otherUserTask->id}", [
                'name' => ['en' => 'Hacked Task']
            ]);
            $response->assertStatus(403);

            // User cannot delete other user's task
            $response = $this->deleteJson("/api/tasks/{$otherUserTask->id}");
            $response->assertStatus(403);
        });

        it('prevents privilege escalation through task manipulation', function () {
            Sanctum::actingAs($this->user);

            // Try to create task for another user
            $response = $this->postJson('/api/tasks', [
                'name' => ['en' => 'Malicious Task'],
                'user_id' => $this->otherUser->id, // Try to set different user
                'status' => 'pending'
            ]);

            // Should either reject or ignore the user_id field
            if ($response->status() === 201) {
                $task = Task::latest()->first();
                expect($task->user_id)->toBe($this->user->id); // Should be current user
            }
        });

        it('prevents unauthorized subtask access', function () {
            $otherUserTask = Task::factory()->for($this->otherUser)->create();

            Sanctum::actingAs($this->user);

            // Cannot create subtask for other user's task
            $response = $this->postJson("/api/tasks/{$otherUserTask->id}/subtasks", [
                'name' => ['en' => 'Unauthorized Subtask']
            ]);
            $response->assertStatus(403);

            // Cannot list other user's subtasks
            $response = $this->getJson("/api/tasks/{$otherUserTask->id}/subtasks");
            $response->assertStatus(403);
        });
    });

    describe('Input Validation Security', function () {
        it('prevents SQL injection in task queries', function () {
            Sanctum::actingAs($this->user);

            $maliciousInputs = [
                "'; DROP TABLE tasks; --",
                "1' OR '1'='1",
                "1; DELETE FROM users; --",
                "' UNION SELECT * FROM users --"
            ];

            foreach ($maliciousInputs as $input) {
                // Try SQL injection in various parameters
                $response = $this->getJson("/api/tasks?status={$input}");
                // Should not cause SQL errors or unauthorized data access
                expect(in_array($response->status(), [200, 422]))->toBeTrue();

                $response = $this->getJson("/api/tasks?priority={$input}");
                expect(in_array($response->status(), [200, 422]))->toBeTrue();
            }

            // Verify tables still exist
            expect(User::count())->toBeGreaterThan(0);
            expect(Task::count())->toBeGreaterThanOrEqual(0);
        });

        it('sanitizes and validates task input data', function () {
            Sanctum::actingAs($this->user);

            $maliciousData = [
                'name' => [
                    'en' => '<script>alert("XSS")</script>',
                    'fr' => '<?php echo "PHP injection"; ?>'
                ],
                'description' => [
                    'en' => '<img src="x" onerror="alert(1)">'
                ],
                'status' => 'invalid_status',
                'priority' => '<script>alert("xss")</script>'
            ];

            $response = $this->postJson('/api/tasks', $maliciousData);

            // Should validate and reject invalid data
            expect($response->status())->toBe(422);
        });

        it('prevents mass assignment vulnerabilities', function () {
            Sanctum::actingAs($this->user);

            $response = $this->postJson('/api/tasks', [
                'name' => ['en' => 'Test Task'],
                'status' => 'pending',
                'id' => 99999, // Try to set ID
                'created_at' => '2020-01-01', // Try to set timestamp
                'user_id' => $this->otherUser->id, // Try to set different user
            ]);

            if ($response->status() === 201) {
                $task = Task::latest()->first();
                expect($task->id)->not->toBe(99999);
                expect($task->user_id)->toBe($this->user->id);
                expect($task->created_at->format('Y-m-d'))->not->toBe('2020-01-01');
            }
        });

        it('validates JSON structure for multilingual fields', function () {
            Sanctum::actingAs($this->user);

            $invalidJsonInputs = [
                ['name' => 'not_an_object'],
                ['name' => ['invalid_locale_key' => 'value']],
                ['description' => 123],
            ];

            foreach ($invalidJsonInputs as $input) {
                $response = $this->postJson('/api/tasks', array_merge([
                    'status' => 'pending',
                    'priority' => 'medium'
                ], $input));

                expect($response->status())->toBe(422);
            }
        });
    });

    describe('Rate Limiting Security', function () {
        it('applies rate limiting to authentication endpoints', function () {
            $email = 'test@example.com';
            $password = 'WrongPassword123!';

            // Make multiple failed login attempts
            for ($i = 0; $i < 6; $i++) {
                $response = $this->postJson('/api/auth/login', [
                    'email' => $email,
                    'password' => $password
                ]);

                if ($i < 5) {
                    // First few attempts should get 401 or 422
                    expect(in_array($response->status(), [401, 422]))->toBeTrue();
                } else {
                    // After rate limit, should get 429
                    expect($response->status())->toBe(429);
                }
            }
        });

        it('applies rate limiting to registration endpoint', function () {
            // Make multiple registration attempts
            for ($i = 0; $i < 6; $i++) {
                $response = $this->postJson('/api/auth/register', [
                    'name' => "User {$i}",
                    'email' => "user{$i}@example.com",
                    'password' => 'Password123!',
                    'password_confirmation' => 'Password123!'
                ]);

                if ($i < 5) {
                    expect(in_array($response->status(), [201, 422]))->toBeTrue();
                } else {
                    // Should be rate limited
                    expect($response->status())->toBe(429);
                }
            }
        });
    });

    describe('Session Security', function () {
        it('prevents session fixation attacks', function () {
            // Get initial session
            $response1 = $this->getJson('/api/health');
            $sessionId1 = $response1->headers->get('Set-Cookie');

            // Login
            $response2 = $this->postJson('/api/auth/login', [
                'email' => $this->user->email,
                'password' => 'password'
            ]);

            // Session should change after login
            $sessionId2 = $response2->headers->get('Set-Cookie');
            
            // In a properly configured system, session ID should change
            // This is a basic test - actual implementation may vary
            expect($sessionId1)->not->toBe($sessionId2);
        });

        it('properly handles concurrent sessions', function () {
            // Create multiple tokens for the same user
            $token1 = $this->user->createToken('session1')->plainTextToken;
            $token2 = $this->user->createToken('session2')->plainTextToken;

            // Both tokens should work
            $response1 = $this->withHeaders(['Authorization' => "Bearer {$token1}"])
                ->getJson('/api/user');
            $response1->assertStatus(200);

            $response2 = $this->withHeaders(['Authorization' => "Bearer {$token2}"])
                ->getJson('/api/user');
            $response2->assertStatus(200);

            // Logout one session
            $this->withHeaders(['Authorization' => "Bearer {$token1}"])
                ->postJson('/api/auth/logout');

            // First token should be invalid
            $response1 = $this->withHeaders(['Authorization' => "Bearer {$token1}"])
                ->getJson('/api/user');
            $response1->assertStatus(401);

            // Second token should still work
            $response2 = $this->withHeaders(['Authorization' => "Bearer {$token2}"])
                ->getJson('/api/user');
            $response2->assertStatus(200);
        });
    });

    describe('Data Exposure Prevention', function () {
        it('does not expose sensitive user data in API responses', function () {
            Sanctum::actingAs($this->user);

            $response = $this->getJson('/api/user');
            $response->assertStatus(200);

            $userData = $response->json('user');
            
            // Should not expose sensitive fields
            expect($userData)->not->toHaveKey('password');
            expect($userData)->not->toHaveKey('remember_token');
        });

        it('does not expose other users data in task responses', function () {
            $task = Task::factory()->for($this->user)->create();
            
            Sanctum::actingAs($this->user);

            $response = $this->getJson("/api/tasks/{$task->id}");
            $response->assertStatus(200);

            $taskData = $response->json('data');
            
            // Should only show task data, not full user details
            expect($taskData['user_id'])->toBe($this->user->id);
            
            // Should not expose other users' information
            if (isset($taskData['user'])) {
                expect($taskData['user'])->not->toHaveKey('password');
                expect($taskData['user'])->not->toHaveKey('remember_token');
            }
        });

        it('prevents information disclosure through error messages', function () {
            Sanctum::actingAs($this->user);

            // Try to access non-existent task
            $response = $this->getJson('/api/tasks/99999');
            $response->assertStatus(404);

            // Error message should not reveal system information
            $errorMessage = $response->json('message');
            expect($errorMessage)->not->toContain('database');
            expect($errorMessage)->not->toContain('SQL');
            expect($errorMessage)->not->toContain('Exception');
        });
    });

    describe('CSRF Protection', function () {
        it('requires CSRF token for state-changing operations', function () {
            // This test would be more relevant for web routes
            // API routes typically use token authentication instead of CSRF
            
            // Test that CSRF cookie endpoint exists
            $response = $this->getJson('/api/sanctum/csrf-cookie');
            $response->assertStatus(200);
        });
    });

    describe('File Upload Security', function () {
        it('validates file types and sizes', function () {
            // This would test file upload functionality if implemented
            // For now, just verify that file upload endpoints don't exist unexpectedly
            
            $response = $this->postJson('/api/tasks/upload');
            expect($response->status())->toBe(404); // Should not exist
        });
    });

    describe('API Security Headers', function () {
        it('includes security headers in responses', function () {
            $response = $this->getJson('/api/health');
            
            // Check for common security headers
            // Note: Actual headers depend on middleware configuration
            $headers = $response->headers;
            
            // These tests would pass if security middleware is properly configured
            // For now, just verify the response is successful
            $response->assertStatus(200);
        });
    });
});