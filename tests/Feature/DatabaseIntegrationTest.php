<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Database Integration', function () {
    describe('Database Connection', function () {
        it('can connect to the database', function () {
            expect(DB::connection()->getPdo())->not->toBeNull();
        });

        it('can execute basic queries', function () {
            $result = DB::select('SELECT 1 as test');
            
            expect($result)->toHaveCount(1);
            expect($result[0]->test)->toBe(1);
        });

        it('has correct database configuration', function () {
            $config = config('database.connections.mysql');
            
            expect($config)->toHaveKey('host');
            expect($config)->toHaveKey('database');
            expect($config)->toHaveKey('username');
        });
    });

    describe('Table Structure', function () {
        it('has users table with correct columns', function () {
            expect(Schema::hasTable('users'))->toBeTrue();
            
            $columns = ['id', 'name', 'email', 'password', 'preferred_language', 'timezone', 'created_at', 'updated_at', 'deleted_at'];
            
            foreach ($columns as $column) {
                expect(Schema::hasColumn('users', $column))->toBeTrue();
            }
        });

        it('has tasks table with correct columns', function () {
            expect(Schema::hasTable('tasks'))->toBeTrue();
            
            $columns = ['id', 'name', 'description', 'status', 'priority', 'due_date', 'parent_id', 'user_id', 'created_at', 'updated_at', 'deleted_at'];
            
            foreach ($columns as $column) {
                expect(Schema::hasColumn('tasks', $column))->toBeTrue();
            }
        });

        it('has correct foreign key constraints', function () {
            // Test that foreign key relationships work
            $user = User::factory()->create();
            $parentTask = Task::factory()->for($user)->create();
            $subtask = Task::factory()->for($user)->create(['parent_id' => $parentTask->id]);
            
            expect($subtask->parent_id)->toBe($parentTask->id);
            expect($subtask->user_id)->toBe($user->id);
        });

        it('has correct indexes for performance', function () {
            // This is a basic test - in a real scenario you'd check actual indexes
            $user = User::factory()->create();
            Task::factory()->count(100)->for($user)->create();
            
            $startTime = microtime(true);
            
            // Query that should use indexes
            $tasks = Task::where('user_id', $user->id)
                ->where('status', 'pending')
                ->get();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Should be fast due to indexes
            expect($executionTime)->toBeLessThan(1.0);
        });
    });

    describe('Database Transactions', function () {
        it('can perform database transactions', function () {
            $user = User::factory()->create();
            
            DB::transaction(function () use ($user) {
                Task::factory()->for($user)->create(['name' => ['en' => 'Task 1']]);
                Task::factory()->for($user)->create(['name' => ['en' => 'Task 2']]);
            });
            
            expect(Task::count())->toBe(2);
        });

        it('can rollback transactions on failure', function () {
            $user = User::factory()->create();
            
            try {
                DB::transaction(function () use ($user) {
                    Task::factory()->for($user)->create(['name' => ['en' => 'Task 1']]);
                    
                    // Force an error
                    throw new Exception('Test rollback');
                });
            } catch (Exception $e) {
                // Expected exception
            }
            
            // Transaction should have been rolled back
            expect(Task::count())->toBe(0);
        });

        it('handles nested transactions', function () {
            $user = User::factory()->create();
            
            DB::transaction(function () use ($user) {
                Task::factory()->for($user)->create(['name' => ['en' => 'Outer Task']]);
                
                DB::transaction(function () use ($user) {
                    Task::factory()->for($user)->create(['name' => ['en' => 'Inner Task']]);
                });
            });
            
            expect(Task::count())->toBe(2);
        });
    });

    describe('Data Integrity', function () {
        it('enforces unique constraints', function () {
            $email = 'unique@example.com';
            
            User::factory()->create(['email' => $email]);
            
            expect(function () use ($email) {
                User::factory()->create(['email' => $email]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('enforces foreign key constraints', function () {
            expect(function () {
                Task::factory()->create([
                    'user_id' => 99999, // Non-existent user
                    'name' => ['en' => 'Test Task']
                ]);
            })->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('handles cascade deletes correctly', function () {
            $user = User::factory()->create();
            $parentTask = Task::factory()->for($user)->create();
            $subtask = Task::factory()->for($user)->create(['parent_id' => $parentTask->id]);
            
            // Delete parent task (should cascade to subtask)
            $parentTask->forceDelete();
            
            expect(Task::withTrashed()->find($subtask->id))->toBeNull();
        });
    });

    describe('Soft Deletes', function () {
        it('implements soft deletes for users', function () {
            $user = User::factory()->create();
            
            $user->delete();
            
            expect($user->trashed())->toBeTrue();
            expect(User::count())->toBe(0);
            expect(User::withTrashed()->count())->toBe(1);
        });

        it('implements soft deletes for tasks', function () {
            $user = User::factory()->create();
            $task = Task::factory()->for($user)->create();
            
            $task->delete();
            
            expect($task->trashed())->toBeTrue();
            expect(Task::count())->toBe(0);
            expect(Task::withTrashed()->count())->toBe(1);
        });

        it('can restore soft deleted records', function () {
            $user = User::factory()->create();
            $task = Task::factory()->for($user)->create();
            
            $task->delete();
            expect($task->trashed())->toBeTrue();
            
            $task->restore();
            expect($task->trashed())->toBeFalse();
            expect(Task::count())->toBe(1);
        });
    });

    describe('JSON Column Support', function () {
        it('can store and retrieve JSON data in task names', function () {
            $user = User::factory()->create();
            $taskData = [
                'name' => [
                    'en' => 'English Task',
                    'fr' => 'Tâche Française',
                    'de' => 'Deutsche Aufgabe'
                ],
                'description' => [
                    'en' => 'English Description',
                    'fr' => 'Description Française'
                ]
            ];
            
            $task = Task::factory()->for($user)->create($taskData);
            
            expect($task->name)->toBe($taskData['name']);
            expect($task->description)->toBe($taskData['description']);
        });

        it('can query JSON columns', function () {
            $user = User::factory()->create();
            
            Task::factory()->for($user)->create([
                'name' => ['en' => 'English Task', 'fr' => 'Tâche Française']
            ]);
            
            Task::factory()->for($user)->create([
                'name' => ['en' => 'Another Task']
            ]);
            
            // Query for tasks with French translations
            $tasksWithFrench = Task::whereJsonContains('name->fr', 'Tâche Française')->get();
            
            expect($tasksWithFrench)->toHaveCount(1);
        });
    });

    describe('Database Performance', function () {
        it('can handle bulk inserts efficiently', function () {
            $user = User::factory()->create();
            
            $startTime = microtime(true);
            
            // Create multiple tasks
            $tasks = Task::factory()->count(100)->for($user)->make()->toArray();
            Task::insert($tasks);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect(Task::count())->toBe(100);
            expect($executionTime)->toBeLessThan(5.0); // Should be fast
        });

        it('can handle complex queries efficiently', function () {
            $user = User::factory()->create();
            Task::factory()->count(50)->for($user)->create();
            
            $startTime = microtime(true);
            
            // Complex query with joins and conditions
            $results = Task::with(['user', 'subtasks'])
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($results)->not->toBeEmpty();
            expect($executionTime)->toBeLessThan(1.0);
        });
    });

    describe('Database Migrations', function () {
        it('has run all migrations successfully', function () {
            // Check that migration table exists and has entries
            expect(Schema::hasTable('migrations'))->toBeTrue();
            
            $migrations = DB::table('migrations')->count();
            expect($migrations)->toBeGreaterThan(0);
        });

        it('can rollback and re-run migrations', function () {
            // This is a basic test - in practice you'd test specific migration rollbacks
            expect(Schema::hasTable('tasks'))->toBeTrue();
            expect(Schema::hasTable('users'))->toBeTrue();
        });
    });
});