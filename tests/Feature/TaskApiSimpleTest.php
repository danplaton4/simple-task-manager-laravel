<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Task API Simple', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    });

    describe('Basic CRUD Operations', function () {
        it('requires authentication for task endpoints', function () {
            $response = $this->getJson('/api/tasks');
            $response->assertStatus(401);
        });

        it('can list user tasks when authenticated', function () {
            Sanctum::actingAs($this->user);
            
            Task::factory()->for($this->user)->create();
            Task::factory()->for($this->otherUser)->create(); // Should not appear

            $response = $this->getJson('/api/tasks');

            $response->assertStatus(200)
                ->assertJsonStructure(['data']);
            
            // Should only see own tasks
            expect(count($response->json('data')))->toBe(1);
        });

        it('can show specific task when authenticated and authorized', function () {
            Sanctum::actingAs($this->user);
            
            $task = Task::factory()->for($this->user)->create();

            $response = $this->getJson("/api/tasks/{$task->id}");

            $response->assertStatus(200)
                ->assertJsonStructure(['data' => ['id', 'name', 'status']]);
        });

        it('cannot show other user task', function () {
            Sanctum::actingAs($this->user);
            
            $otherTask = Task::factory()->for($this->otherUser)->create();

            $response = $this->getJson("/api/tasks/{$otherTask->id}");

            $response->assertStatus(403);
        });

        it('can create task when authenticated', function () {
            Sanctum::actingAs($this->user);

            $taskData = [
                'name' => ['en' => 'Test Task'],
                'status' => 'pending',
                'priority' => 'medium'
            ];

            $response = $this->postJson('/api/tasks', $taskData);

            $response->assertStatus(201);
            
            $this->assertDatabaseHas('tasks', [
                'user_id' => $this->user->id,
                'status' => 'pending'
            ]);
        });

        it('can update own task', function () {
            Sanctum::actingAs($this->user);
            
            $task = Task::factory()->for($this->user)->create(['status' => 'pending']);

            $response = $this->putJson("/api/tasks/{$task->id}", [
                'status' => 'completed'
            ]);

            $response->assertStatus(200);
            
            $this->assertDatabaseHas('tasks', [
                'id' => $task->id,
                'status' => 'completed'
            ]);
        });

        it('cannot update other user task', function () {
            Sanctum::actingAs($this->user);
            
            $otherTask = Task::factory()->for($this->otherUser)->create();

            $response = $this->putJson("/api/tasks/{$otherTask->id}", [
                'status' => 'completed'
            ]);

            $response->assertStatus(403);
        });

        it('can soft delete own task', function () {
            Sanctum::actingAs($this->user);
            
            $task = Task::factory()->for($this->user)->create();

            $response = $this->deleteJson("/api/tasks/{$task->id}");

            $response->assertStatus(200);
            
            expect($task->fresh()->trashed())->toBeTrue();
        });

        it('cannot delete other user task', function () {
            Sanctum::actingAs($this->user);
            
            $otherTask = Task::factory()->for($this->otherUser)->create();

            $response = $this->deleteJson("/api/tasks/{$otherTask->id}");

            $response->assertStatus(403);
        });
    });

    describe('Subtask Management', function () {
        it('can create subtask', function () {
            Sanctum::actingAs($this->user);
            
            $parentTask = Task::factory()->for($this->user)->create();

            $response = $this->postJson("/api/tasks/{$parentTask->id}/subtasks", [
                'name' => ['en' => 'Subtask'],
                'status' => 'pending'
            ]);

            $response->assertStatus(201);
            
            $this->assertDatabaseHas('tasks', [
                'parent_id' => $parentTask->id,
                'user_id' => $this->user->id
            ]);
        });

        it('can list subtasks', function () {
            Sanctum::actingAs($this->user);
            
            $parentTask = Task::factory()->for($this->user)->create();
            Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);

            $response = $this->getJson("/api/tasks/{$parentTask->id}/subtasks");

            $response->assertStatus(200)
                ->assertJsonStructure(['data']);
            
            expect(count($response->json('data')))->toBe(1);
        });
    });

    describe('Soft Delete Behavior', function () {
        it('excludes soft deleted tasks from listings', function () {
            Sanctum::actingAs($this->user);
            
            $task = Task::factory()->for($this->user)->create();
            
            // Task should appear in listing
            $response = $this->getJson('/api/tasks');
            expect(count($response->json('data')))->toBe(1);
            
            // Delete task
            $task->delete();
            
            // Task should not appear in listing
            $response = $this->getJson('/api/tasks');
            expect(count($response->json('data')))->toBe(0);
        });

        it('can restore soft deleted task', function () {
            Sanctum::actingAs($this->user);
            
            $task = Task::factory()->for($this->user)->create();
            $task->delete();

            $response = $this->postJson("/api/tasks/{$task->id}/restore");

            $response->assertStatus(200);
            
            expect($task->fresh()->trashed())->toBeFalse();
        });
    });

    describe('Filtering and Pagination', function () {
        it('can filter tasks by status', function () {
            Sanctum::actingAs($this->user);
            
            Task::factory()->for($this->user)->create(['status' => 'pending']);
            Task::factory()->for($this->user)->create(['status' => 'completed']);

            $response = $this->getJson('/api/tasks?status=pending');

            $response->assertStatus(200);
            
            $tasks = $response->json('data');
            expect(count($tasks))->toBe(1);
            expect($tasks[0]['status'])->toBe('pending');
        });

        it('supports pagination', function () {
            Sanctum::actingAs($this->user);
            
            Task::factory()->count(5)->for($this->user)->create();

            $response = $this->getJson('/api/tasks?per_page=3');

            $response->assertStatus(200)
                ->assertJsonStructure(['data', 'links', 'meta']);
            
            expect(count($response->json('data')))->toBe(3);
        });
    });
});