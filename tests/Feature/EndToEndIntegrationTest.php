<?php

use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use App\Jobs\SendTaskNotificationJob;
use App\Mail\TaskCreatedMail;

uses(RefreshDatabase::class);

describe('End-to-End Integration Tests', function () {
    
    beforeEach(function () {
        // Clear Redis cache before each test
        Redis::flushall();
        Mail::fake();
        Queue::fake();
    });

    it('can complete full user registration and task management workflow', function () {
        // Step 1: User Registration
        $userData = [
            'name' => 'Test User',
            'email' => 'integration.test@gmail.com',
            'password' => 'SecureTestPass2024!@#',
            'password_confirmation' => 'SecureTestPass2024!@#',
            'preferred_language' => 'en'
        ];

        $registerResponse = $this->postJson('/api/auth/register', $userData);
        $registerResponse->assertStatus(201);
        $registerResponse->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'preferred_language'],
            'token'
        ]);

        $token = $registerResponse->json('token');
        $user = User::where('email', 'integration.test@gmail.com')->first();
        
        // Verify token exists
        expect($token)->not->toBeNull();

        // Step 2: User Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'integration.test@gmail.com',
            'password' => 'SecureTestPass2024!@#'
        ]);
        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure(['user', 'token']);
        
        // Use login token for subsequent requests
        $token = $loginResponse->json('token');

        // Step 3: Create Parent Task
        $parentTaskData = [
            'name' => [
                'en' => 'Complete Project',
                'fr' => 'Terminer le projet',
                'de' => 'Projekt abschließen'
            ],
            'description' => [
                'en' => 'Main project task',
                'fr' => 'Tâche principale du projet',
                'de' => 'Hauptprojektaufgabe'
            ],
            'status' => 'pending',
            'priority' => 'high'
        ];

        $createTaskResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $parentTaskData);
        
        $createTaskResponse->assertStatus(201);
        $createTaskResponse->assertJsonStructure([
            'data' => ['id', 'name', 'description', 'status', 'priority']
        ]);

        $parentTask = Task::find($createTaskResponse->json('data.id'));

        // Verify queue job was dispatched
        Queue::assertPushed(SendTaskNotificationJob::class);

        // Step 4: Create Subtasks
        $subtaskData = [
            'name' => [
                'en' => 'Research Phase',
                'fr' => 'Phase de recherche',
                'de' => 'Forschungsphase'
            ],
            'description' => [
                'en' => 'Initial research',
                'fr' => 'Recherche initiale',
                'de' => 'Erste Forschung'
            ],
            'status' => 'pending',
            'priority' => 'medium',
            'parent_id' => $parentTask->id
        ];

        $createSubtaskResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $subtaskData);
        
        $createSubtaskResponse->assertStatus(201);
        $subtask = Task::find($createSubtaskResponse->json('data.id'));

        // Step 5: Test Task Listing with Hierarchical Structure
        $listResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/tasks');
        
        $listResponse->assertStatus(200);
        $listResponse->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'status', 'priority', 'subtasks']
            ]
        ]);

        // Verify hierarchical structure
        $tasks = $listResponse->json('data');
        $parentTaskInList = collect($tasks)->firstWhere('id', $parentTask->id);
        expect($parentTaskInList['subtasks'])->toHaveCount(1);
        expect($parentTaskInList['subtasks'][0]['id'])->toBe($subtask->id);

        // Step 6: Update Task Status
        $updateResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/tasks/{$subtask->id}", [
                'name' => $subtask->name,
                'description' => $subtask->description,
                'status' => 'completed',
                'priority' => $subtask->priority,
                'parent_id' => $subtask->parent_id
            ]);
        
        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'data' => ['status' => 'completed']
        ]);

        // Step 7: Test Soft Delete
        $deleteResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->deleteJson("/api/tasks/{$subtask->id}");
        
        $deleteResponse->assertStatus(200);

        // Verify soft delete
        $subtask->refresh();
        expect($subtask->deleted_at)->not->toBeNull();

        // Step 8: Test Task Restore
        $restoreResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson("/api/tasks/{$subtask->id}/restore");
        
        $restoreResponse->assertStatus(200);

        // Verify restore
        $subtask->refresh();
        expect($subtask->deleted_at)->toBeNull();

        // Step 9: Test User Logout
        $logoutResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/auth/logout');
        
        $logoutResponse->assertStatus(200);

        // Verify token is revoked
        $protectedResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/tasks');
        
        $protectedResponse->assertStatus(401);
    });

    it('can handle multilingual functionality across the entire application', function () {
        $user = User::factory()->create(['preferred_language' => 'fr']);
        $token = $user->createToken('test')->plainTextToken;

        // Create task with multilingual content
        $taskData = [
            'name' => [
                'en' => 'English Task',
                'fr' => 'Tâche Française',
                'de' => 'Deutsche Aufgabe'
            ],
            'description' => [
                'en' => 'English description',
                'fr' => 'Description française',
                'de' => 'Deutsche Beschreibung'
            ],
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $createResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->postJson('/api/tasks', $taskData);

        $createResponse->assertStatus(201);

        $taskId = $createResponse->json('data.id');

        // Test French locale
        $frResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'fr'
        ])->getJson("/api/tasks/{$taskId}");

        $frResponse->assertStatus(200);
        expect($frResponse->json('data.name'))->toBe('Tâche Française');
        expect($frResponse->json('data.description'))->toBe('Description française');

        // Test German locale
        $deResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'de'
        ])->getJson("/api/tasks/{$taskId}");

        $deResponse->assertStatus(200);
        expect($deResponse->json('data.name'))->toBe('Deutsche Aufgabe');
        expect($deResponse->json('data.description'))->toBe('Deutsche Beschreibung');

        // Test English locale (fallback)
        $enResponse = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept-Language' => 'en'
        ])->getJson("/api/tasks/{$taskId}");

        $enResponse->assertStatus(200);
        expect($enResponse->json('data.name'))->toBe('English Task');
        expect($enResponse->json('data.description'))->toBe('English description');
    });

    it('can validate email notifications and queue processing', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create task to trigger notification
        $taskData = [
            'name' => ['en' => 'Test Task'],
            'description' => ['en' => 'Test Description'],
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $taskData);

        // Verify notification job was queued
        Queue::assertPushed(SendTaskNotificationJob::class, function ($job) use ($user) {
            return $job->task->user_id === $user->id;
        });

        // Process the queue job
        $task = Task::where('user_id', $user->id)->first();
        $job = new SendTaskNotificationJob($task, 'created');
        $job->handle();

        // Verify email was sent
        Mail::assertQueued(TaskCreatedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('can verify all Docker services work together correctly', function () {
        // Test database connectivity
        $user = User::factory()->create();
        expect($user->exists)->toBeTrue();

        // Test Redis connectivity
        Redis::set('test_key', 'test_value');
        expect(Redis::get('test_key'))->toBe('test_value');
        Redis::del('test_key');

        // Test cache functionality
        $cacheKey = 'test_cache_key';
        $cacheValue = 'test_cache_value';
        
        cache()->put($cacheKey, $cacheValue, 60);
        expect(cache()->get($cacheKey))->toBe($cacheValue);
        cache()->forget($cacheKey);

        // Test session functionality
        session(['test_session' => 'session_value']);
        expect(session('test_session'))->toBe('session_value');
        session()->forget('test_session');

        // Test queue functionality
        Queue::fake();
        dispatch(new SendTaskNotificationJob(Task::factory()->create(), 'test'));
        Queue::assertPushed(SendTaskNotificationJob::class);
    });

    it('can handle complex task hierarchies and relationships', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create parent task
        $parentResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', [
                'name' => ['en' => 'Parent Task'],
                'description' => ['en' => 'Parent Description'],
                'status' => 'pending',
                'priority' => 'high'
            ]);

        $parentId = $parentResponse->json('data.id');

        // Create multiple subtasks
        $subtaskIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $subtaskResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
                ->postJson('/api/tasks', [
                    'name' => ['en' => "Subtask $i"],
                    'description' => ['en' => "Subtask $i Description"],
                    'status' => 'pending',
                    'priority' => 'medium',
                    'parent_id' => $parentId
                ]);
            
            $subtaskIds[] = $subtaskResponse->json('data.id');
        }

        // Create sub-subtasks
        $subSubtaskResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', [
                'name' => ['en' => 'Sub-subtask'],
                'description' => ['en' => 'Sub-subtask Description'],
                'status' => 'pending',
                'priority' => 'low',
                'parent_id' => $subtaskIds[0]
            ]);

        // Verify hierarchical structure
        $listResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/tasks');

        $tasks = $listResponse->json('data');
        $parentTask = collect($tasks)->firstWhere('id', $parentId);
        
        expect($parentTask['subtasks'])->toHaveCount(3);
        
        $firstSubtask = collect($parentTask['subtasks'])->firstWhere('id', $subtaskIds[0]);
        expect($firstSubtask['subtasks'])->toHaveCount(1);
    });

    it('can handle authorization and security correctly', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $token1 = $user1->createToken('test')->plainTextToken;
        $token2 = $user2->createToken('test')->plainTextToken;

        // User 1 creates a task
        $taskResponse = $this->withHeaders(['Authorization' => "Bearer $token1"])
            ->postJson('/api/tasks', [
                'name' => ['en' => 'User 1 Task'],
                'description' => ['en' => 'Private task'],
                'status' => 'pending',
                'priority' => 'medium'
            ]);

        $taskId = $taskResponse->json('data.id');

        // User 2 tries to access User 1's task
        $unauthorizedResponse = $this->withHeaders(['Authorization' => "Bearer $token2"])
            ->getJson("/api/tasks/{$taskId}");

        $unauthorizedResponse->assertStatus(403);

        // User 2 tries to update User 1's task
        $unauthorizedUpdateResponse = $this->withHeaders(['Authorization' => "Bearer $token2"])
            ->putJson("/api/tasks/{$taskId}", [
                'name' => ['en' => 'Hacked Task'],
                'status' => 'completed'
            ]);

        $unauthorizedUpdateResponse->assertStatus(403);

        // User 2 tries to delete User 1's task
        $unauthorizedDeleteResponse = $this->withHeaders(['Authorization' => "Bearer $token2"])
            ->deleteJson("/api/tasks/{$taskId}");

        $unauthorizedDeleteResponse->assertStatus(403);

        // Verify User 1 can still access their task
        $authorizedResponse = $this->withHeaders(['Authorization' => "Bearer $token1"])
            ->getJson("/api/tasks/{$taskId}");

        $authorizedResponse->assertStatus(200);
        expect($authorizedResponse->json('data.name'))->toBe('User 1 Task');
    });
});