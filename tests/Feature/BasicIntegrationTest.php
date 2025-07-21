<?php

use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Basic Integration Tests', function () {
    
    beforeEach(function () {
        Mail::fake();
        Queue::fake();
    });

    it('can register a user', function () {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'password' => 'SecureTestPass2024!@#',
            'password_confirmation' => 'SecureTestPass2024!@#',
            'preferred_language' => 'en'
        ];

        $response = $this->postJson('/api/auth/register', $userData);
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token'
        ]);
    });

    it('can login a user', function () {
        $user = User::factory()->create([
            'email' => 'test@gmail.com',
            'password' => bcrypt('SecureTestPass2024!@#')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@gmail.com',
            'password' => 'SecureTestPass2024!@#'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    });

    it('can create a task with authentication', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $taskData = [
            'name' => ['en' => 'Test Task'],
            'description' => ['en' => 'Test Description'],
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/tasks', $taskData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'description', 'status', 'priority']
        ]);
    });

    it('can list user tasks', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        
        Task::factory()->count(3)->for($user)->create();

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/tasks');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'status', 'priority']
            ]
        ]);
    });

    it('can update a task', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        
        $task = Task::factory()->for($user)->create();

        $updateData = [
            'name' => $task->name,
            'description' => $task->description,
            'status' => 'completed',
            'priority' => $task->priority
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/tasks/{$task->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => ['status' => 'completed']
        ]);
    });

    it('can delete and restore a task', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        
        $task = Task::factory()->for($user)->create();

        // Delete task
        $deleteResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->deleteJson("/api/tasks/{$task->id}");

        $deleteResponse->assertStatus(200);

        // Verify soft delete
        $task->refresh();
        expect($task->deleted_at)->not->toBeNull();

        // Restore task
        $restoreResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson("/api/tasks/{$task->id}/restore");

        $restoreResponse->assertStatus(200);

        // Verify restore
        $task->refresh();
        expect($task->deleted_at)->toBeNull();
    });
});