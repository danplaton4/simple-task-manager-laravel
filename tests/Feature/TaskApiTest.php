<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Task API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    });

    describe('Task CRUD Operations', function () {
        describe('Create Task', function () {
            it('can create a task', function () {
                Sanctum::actingAs($this->user);

                $taskData = [
                    'name' => ['en' => 'Test Task'],
                    'description' => ['en' => 'Test Description'],
                    'status' => 'pending',
                    'priority' => 'medium',
                    'due_date' => now()->addDays(7)->toISOString()
                ];

                $response = $this->postJson('/api/tasks', $taskData);

                $response->assertStatus(201)
                    ->assertJsonStructure([
                        'data' => [
                            'id', 'name', 'description', 'status', 'priority', 
                            'due_date', 'user_id', 'created_at', 'updated_at'
                        ]
                    ]);

                $this->assertDatabaseHas('tasks', [
                    'user_id' => $this->user->id,
                    'status' => 'pending',
                    'priority' => 'medium'
                ]);
            });

            it('validates required fields when creating task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->postJson('/api/tasks', []);

                $response->assertStatus(422)
                    ->assertJsonValidationErrors(['name']);
            });

            it('validates status values when creating task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->postJson('/api/tasks', [
                    'name' => ['en' => 'Test Task'],
                    'status' => 'invalid_status'
                ]);

                $response->assertStatus(422)
                    ->assertJsonValidationErrors(['status']);
            });

            it('validates priority values when creating task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->postJson('/api/tasks', [
                    'name' => ['en' => 'Test Task'],
                    'priority' => 'invalid_priority'
                ]);

                $response->assertStatus(422)
                    ->assertJsonValidationErrors(['priority']);
            });

            it('cannot create task without authentication', function () {
                $response = $this->postJson('/api/tasks', [
                    'name' => ['en' => 'Test Task']
                ]);

                $response->assertStatus(401);
            });
        });

        describe('Read Tasks', function () {
            beforeEach(function () {
                $this->userTask = Task::factory()->for($this->user)->create([
                    'name' => ['en' => 'User Task'],
                    'status' => 'pending'
                ]);
                $this->otherUserTask = Task::factory()->for($this->otherUser)->create([
                    'name' => ['en' => 'Other User Task'],
                    'status' => 'completed'
                ]);
            });

            it('can list user tasks', function () {
                Sanctum::actingAs($this->user);

                $response = $this->getJson('/api/tasks');

                $response->assertStatus(200)
                    ->assertJsonStructure([
                        'data' => [
                            '*' => ['id', 'name', 'description', 'status', 'priority']
                        ]
                    ]);

                // Should only see own tasks
                $taskIds = collect($response->json('data'))->pluck('id')->toArray();
                expect($taskIds)->toContain($this->userTask->id);
                expect($taskIds)->not->toContain($this->otherUserTask->id);
            });

            it('can filter tasks by status', function () {
                Sanctum::actingAs($this->user);

                // Create additional tasks with different statuses
                Task::factory()->for($this->user)->create(['status' => 'completed']);
                Task::factory()->for($this->user)->create(['status' => 'in_progress']);

                $response = $this->getJson('/api/tasks?status=pending');

                $response->assertStatus(200);
                
                $tasks = $response->json('data');
                expect($tasks)->toHaveCount(1);
                expect($tasks[0]['status'])->toBe('pending');
            });

            it('can filter tasks by priority', function () {
                Sanctum::actingAs($this->user);

                Task::factory()->for($this->user)->create(['priority' => 'high']);
                Task::factory()->for($this->user)->create(['priority' => 'low']);

                $response = $this->getJson('/api/tasks?priority=high');

                $response->assertStatus(200);
                
                $tasks = $response->json('data');
                $highPriorityTasks = collect($tasks)->where('priority', 'high');
                expect($highPriorityTasks)->not->toBeEmpty();
            });

            it('can show specific task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->getJson("/api/tasks/{$this->userTask->id}");

                $response->assertStatus(200)
                    ->assertJson([
                        'data' => [
                            'id' => $this->userTask->id,
                            'name' => $this->userTask->name,
                            'status' => $this->userTask->status
                        ]
                    ]);
            });

            it('cannot show other user task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->getJson("/api/tasks/{$this->otherUserTask->id}");

                $response->assertStatus(403);
            });

            it('returns 404 for non-existent task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->getJson('/api/tasks/99999');

                $response->assertStatus(404);
            });
        });

        describe('Update Task', function () {
            beforeEach(function () {
                $this->task = Task::factory()->for($this->user)->create([
                    'name' => ['en' => 'Original Task'],
                    'status' => 'pending'
                ]);
            });

            it('can update own task', function () {
                Sanctum::actingAs($this->user);

                $updateData = [
                    'name' => ['en' => 'Updated Task'],
                    'status' => 'in_progress',
                    'priority' => 'high'
                ];

                $response = $this->putJson("/api/tasks/{$this->task->id}", $updateData);

                $response->assertStatus(200)
                    ->assertJson([
                        'data' => [
                            'id' => $this->task->id,
                            'name' => ['en' => 'Updated Task'],
                            'status' => 'in_progress',
                            'priority' => 'high'
                        ]
                    ]);

                $this->assertDatabaseHas('tasks', [
                    'id' => $this->task->id,
                    'status' => 'in_progress',
                    'priority' => 'high'
                ]);
            });

            it('cannot update other user task', function () {
                Sanctum::actingAs($this->user);
                $otherTask = Task::factory()->for($this->otherUser)->create();

                $response = $this->putJson("/api/tasks/{$otherTask->id}", [
                    'name' => ['en' => 'Hacked Task']
                ]);

                $response->assertStatus(403);
            });

            it('validates update data', function () {
                Sanctum::actingAs($this->user);

                $response = $this->putJson("/api/tasks/{$this->task->id}", [
                    'status' => 'invalid_status'
                ]);

                $response->assertStatus(422)
                    ->assertJsonValidationErrors(['status']);
            });
        });

        describe('Delete Task', function () {
            beforeEach(function () {
                $this->task = Task::factory()->for($this->user)->create();
            });

            it('can soft delete own task', function () {
                Sanctum::actingAs($this->user);

                $response = $this->deleteJson("/api/tasks/{$this->task->id}");

                $response->assertStatus(200)
                    ->assertJson(['message' => 'Task deleted successfully']);

                // Task should be soft deleted
                expect($this->task->fresh()->trashed())->toBeTrue();
                $this->assertSoftDeleted('tasks', ['id' => $this->task->id]);
            });

            it('cannot delete other user task', function () {
                Sanctum::actingAs($this->user);
                $otherTask = Task::factory()->for($this->otherUser)->create();

                $response = $this->deleteJson("/api/tasks/{$otherTask->id}");

                $response->assertStatus(403);
            });

            it('can restore soft deleted task', function () {
                Sanctum::actingAs($this->user);

                // First delete the task
                $this->task->delete();
                expect($this->task->fresh()->trashed())->toBeTrue();

                // Then restore it
                $response = $this->postJson("/api/tasks/{$this->task->id}/restore");

                $response->assertStatus(200)
                    ->assertJson(['message' => 'Task restored successfully']);

                expect($this->task->fresh()->trashed())->toBeFalse();
            });
        });
    });

    describe('Subtask Management', function () {
        beforeEach(function () {
            $this->parentTask = Task::factory()->for($this->user)->create([
                'name' => ['en' => 'Parent Task']
            ]);
        });

        it('can create subtask', function () {
            Sanctum::actingAs($this->user);

            $subtaskData = [
                'name' => ['en' => 'Subtask'],
                'description' => ['en' => 'Subtask Description'],
                'status' => 'pending'
            ];

            $response = $this->postJson("/api/tasks/{$this->parentTask->id}/subtasks", $subtaskData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => ['id', 'name', 'parent_id']
                ]);

            $this->assertDatabaseHas('tasks', [
                'parent_id' => $this->parentTask->id,
                'user_id' => $this->user->id
            ]);
        });

        it('can list subtasks', function () {
            Sanctum::actingAs($this->user);

            $subtask1 = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);
            $subtask2 = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);

            $response = $this->getJson("/api/tasks/{$this->parentTask->id}/subtasks");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'parent_id']
                    ]
                ]);

            $subtaskIds = collect($response->json('data'))->pluck('id')->toArray();
            expect($subtaskIds)->toContain($subtask1->id, $subtask2->id);
        });

        it('prevents creating subtask of subtask', function () {
            Sanctum::actingAs($this->user);

            $subtask = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);

            $response = $this->postJson("/api/tasks/{$subtask->id}/subtasks", [
                'name' => ['en' => 'Deep Subtask']
            ]);

            $response->assertStatus(422);
        });

        it('can move subtask to different parent', function () {
            Sanctum::actingAs($this->user);

            $newParent = Task::factory()->for($this->user)->create();
            $subtask = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);

            $response = $this->putJson("/api/subtasks/{$subtask->id}/move", [
                'new_parent_id' => $newParent->id
            ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('tasks', [
                'id' => $subtask->id,
                'parent_id' => $newParent->id
            ]);
        });
    });

    describe('Authorization', function () {
        it('requires authentication for all task endpoints', function () {
            $endpoints = [
                ['GET', '/api/tasks'],
                ['POST', '/api/tasks'],
                ['GET', '/api/tasks/1'],
                ['PUT', '/api/tasks/1'],
                ['DELETE', '/api/tasks/1'],
            ];

            foreach ($endpoints as [$method, $url]) {
                $response = $this->json($method, $url);
                expect($response->status())->toBe(401);
            }
        });

        it('enforces task ownership', function () {
            Sanctum::actingAs($this->user);
            $otherUserTask = Task::factory()->for($this->otherUser)->create();

            $endpoints = [
                ['GET', "/api/tasks/{$otherUserTask->id}"],
                ['PUT', "/api/tasks/{$otherUserTask->id}"],
                ['DELETE', "/api/tasks/{$otherUserTask->id}"],
            ];

            foreach ($endpoints as [$method, $url]) {
                $response = $this->json($method, $url, ['name' => ['en' => 'Test']]);
                expect($response->status())->toBe(403);
            }
        });
    });

    describe('Pagination and Filtering', function () {
        beforeEach(function () {
            // Create multiple tasks for pagination testing
            Task::factory()->count(15)->for($this->user)->create();
        });

        it('paginates task results', function () {
            Sanctum::actingAs($this->user);

            $response = $this->getJson('/api/tasks?per_page=10');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'links',
                    'meta' => ['current_page', 'per_page', 'total']
                ]);

            expect($response->json('data'))->toHaveCount(10);
            expect($response->json('meta.total'))->toBe(15);
        });

        it('can navigate through pages', function () {
            Sanctum::actingAs($this->user);

            $page1 = $this->getJson('/api/tasks?per_page=10&page=1');
            $page2 = $this->getJson('/api/tasks?per_page=10&page=2');

            $page1->assertStatus(200);
            $page2->assertStatus(200);

            $page1Ids = collect($page1->json('data'))->pluck('id')->toArray();
            $page2Ids = collect($page2->json('data'))->pluck('id')->toArray();

            // Pages should have different tasks
            expect(array_intersect($page1Ids, $page2Ids))->toBeEmpty();
        });
    });

    describe('Soft Delete Behavior', function () {
        beforeEach(function () {
            $this->task = Task::factory()->for($this->user)->create();
        });

        it('excludes soft deleted tasks from listings', function () {
            Sanctum::actingAs($this->user);

            // Verify task appears in listing
            $response = $this->getJson('/api/tasks');
            $taskIds = collect($response->json('data'))->pluck('id')->toArray();
            expect($taskIds)->toContain($this->task->id);

            // Delete task
            $this->task->delete();

            // Verify task no longer appears in listing
            $response = $this->getJson('/api/tasks');
            $taskIds = collect($response->json('data'))->pluck('id')->toArray();
            expect($taskIds)->not->toContain($this->task->id);
        });

        it('returns 404 for soft deleted task show', function () {
            Sanctum::actingAs($this->user);

            $this->task->delete();

            $response = $this->getJson("/api/tasks/{$this->task->id}");

            $response->assertStatus(404);
        });

        it('can restore and access soft deleted task', function () {
            Sanctum::actingAs($this->user);

            $this->task->delete();

            // Restore task
            $response = $this->postJson("/api/tasks/{$this->task->id}/restore");
            $response->assertStatus(200);

            // Should be able to access again
            $response = $this->getJson("/api/tasks/{$this->task->id}");
            $response->assertStatus(200);
        });
    });
});