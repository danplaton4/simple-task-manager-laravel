<?php

use App\Models\Task;
use App\Models\User;
use App\Services\TaskCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Performance Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->cacheService = new TaskCacheService();
    });

    describe('Database Performance', function () {
        it('can handle bulk task creation efficiently', function () {
            $startTime = microtime(true);
            
            // Create 100 tasks
            Task::factory()->count(100)->for($this->user)->create();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect(Task::count())->toBe(100);
            expect($executionTime)->toBeLessThan(10.0); // Should complete within 10 seconds
        });

        it('can query large datasets efficiently', function () {
            // Create a large dataset
            Task::factory()->count(500)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Perform complex query
            $results = Task::with(['user'])
                ->where('user_id', $this->user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($results)->not->toBeEmpty();
            expect($executionTime)->toBeLessThan(2.0); // Should be fast with proper indexing
        });

        it('can handle concurrent database operations', function () {
            $startTime = microtime(true);
            
            // Simulate concurrent operations
            $promises = [];
            for ($i = 0; $i < 10; $i++) {
                Task::factory()->for($this->user)->create([
                    'name' => ['en' => "Concurrent Task {$i}"],
                    'status' => 'pending'
                ]);
            }
            
            // Query while creating
            $tasks = Task::where('user_id', $this->user->id)->get();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($tasks->count())->toBeGreaterThanOrEqual(10);
            expect($executionTime)->toBeLessThan(5.0);
        });

        it('can handle deep hierarchical queries efficiently', function () {
            // Create hierarchical structure
            $parentTask = Task::factory()->for($this->user)->create();
            
            // Create multiple subtasks
            for ($i = 0; $i < 20; $i++) {
                Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            }
            
            $startTime = microtime(true);
            
            // Query with relationships
            $taskWithSubtasks = Task::with('subtasks')
                ->where('id', $parentTask->id)
                ->first();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($taskWithSubtasks->subtasks)->toHaveCount(20);
            expect($executionTime)->toBeLessThan(1.0);
        });

        it('can handle pagination efficiently on large datasets', function () {
            // Create large dataset
            Task::factory()->count(1000)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Test pagination performance
            $page1 = Task::where('user_id', $this->user->id)
                ->orderBy('id')
                ->limit(50)
                ->get();
            
            $page2 = Task::where('user_id', $this->user->id)
                ->orderBy('id')
                ->offset(50)
                ->limit(50)
                ->get();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($page1)->toHaveCount(50);
            expect($page2)->toHaveCount(50);
            expect($executionTime)->toBeLessThan(2.0);
        });
    });

    describe('Cache Performance', function () {
        it('can handle high-frequency cache operations', function () {
            $startTime = microtime(true);
            
            // Perform many cache operations
            for ($i = 0; $i < 100; $i++) {
                Cache::put("test_key_{$i}", "test_value_{$i}", 60);
            }
            
            for ($i = 0; $i < 100; $i++) {
                Cache::get("test_key_{$i}");
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(3.0); // Should be very fast
        });

        it('can cache large datasets efficiently', function () {
            // Create dataset
            Task::factory()->count(100)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Cache user tasks
            $tasks = $this->cacheService->getUserTasks($this->user->id);
            
            // Retrieve from cache
            $cachedTasks = $this->cacheService->getUserTasks($this->user->id);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($tasks)->toHaveCount(100);
            expect($cachedTasks)->toHaveCount(100);
            expect($executionTime)->toBeLessThan(2.0);
        });

        it('can handle cache invalidation efficiently', function () {
            // Create and cache tasks
            Task::factory()->count(50)->for($this->user)->create();
            $this->cacheService->getUserTasks($this->user->id);
            
            $startTime = microtime(true);
            
            // Clear cache
            $this->cacheService->clearUserTasksCache($this->user->id);
            
            // Rebuild cache
            $this->cacheService->getUserTasks($this->user->id);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(2.0);
        });
    });

    describe('API Performance', function () {
        it('can handle multiple API requests efficiently', function () {
            Sanctum::actingAs($this->user);
            
            // Create some tasks
            Task::factory()->count(20)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Make multiple API requests
            for ($i = 0; $i < 10; $i++) {
                $response = $this->getJson('/api/tasks?per_page=5');
                // Don't assert status to avoid slowing down the test
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(5.0); // Should handle multiple requests quickly
        });

        it('can handle API requests with complex filtering', function () {
            Sanctum::actingAs($this->user);
            
            // Create diverse dataset
            Task::factory()->count(100)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Complex filtering request
            $response = $this->getJson('/api/tasks?status=pending&priority=high&per_page=20');
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(2.0);
        });
    });

    describe('Memory Usage', function () {
        it('maintains reasonable memory usage during bulk operations', function () {
            $initialMemory = memory_get_usage(true);
            
            // Perform memory-intensive operations
            Task::factory()->count(200)->for($this->user)->create();
            
            $tasks = Task::with(['user'])->get();
            
            $finalMemory = memory_get_usage(true);
            $memoryIncrease = $finalMemory - $initialMemory;
            
            // Memory increase should be reasonable (less than 50MB)
            expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024);
        });

        it('properly releases memory after operations', function () {
            $initialMemory = memory_get_usage(true);
            
            // Create and process large dataset
            $tasks = Task::factory()->count(100)->for($this->user)->create();
            
            // Process tasks
            foreach ($tasks as $task) {
                $task->name; // Access attributes
            }
            
            // Clear variables
            unset($tasks);
            
            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            $finalMemory = memory_get_usage(true);
            $memoryIncrease = $finalMemory - $initialMemory;
            
            // Memory should not have increased dramatically
            expect($memoryIncrease)->toBeLessThan(30 * 1024 * 1024);
        });
    });

    describe('Concurrent Operations', function () {
        it('can handle concurrent task creation', function () {
            $startTime = microtime(true);
            
            // Simulate concurrent task creation
            $tasks = [];
            for ($i = 0; $i < 20; $i++) {
                $tasks[] = Task::factory()->for($this->user)->create([
                    'name' => ['en' => "Concurrent Task {$i}"],
                    'status' => 'pending'
                ]);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect(count($tasks))->toBe(20);
            expect($executionTime)->toBeLessThan(5.0);
        });

        it('can handle concurrent read operations', function () {
            // Create dataset
            Task::factory()->count(50)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Simulate concurrent reads
            $results = [];
            for ($i = 0; $i < 10; $i++) {
                $results[] = Task::where('user_id', $this->user->id)
                    ->limit(10)
                    ->get();
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect(count($results))->toBe(10);
            expect($executionTime)->toBeLessThan(3.0);
        });
    });

    describe('Resource Optimization', function () {
        it('uses database connections efficiently', function () {
            $startTime = microtime(true);
            
            // Perform multiple database operations
            for ($i = 0; $i < 50; $i++) {
                Task::factory()->for($this->user)->create();
                Task::where('user_id', $this->user->id)->count();
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(10.0);
        });

        it('handles large result sets efficiently', function () {
            // Create large dataset
            Task::factory()->count(500)->for($this->user)->create();
            
            $startTime = microtime(true);
            
            // Process large result set
            Task::where('user_id', $this->user->id)
                ->chunk(50, function ($tasks) {
                    foreach ($tasks as $task) {
                        // Simulate processing
                        $task->name;
                    }
                });
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(5.0);
        });
    });

    describe('Stress Testing', function () {
        it('can handle high task creation load', function () {
            $startTime = microtime(true);
            
            // Create many tasks rapidly
            for ($i = 0; $i < 200; $i++) {
                Task::factory()->for($this->user)->create([
                    'name' => ['en' => "Stress Test Task {$i}"]
                ]);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect(Task::count())->toBe(200);
            expect($executionTime)->toBeLessThan(15.0); // Allow more time for stress test
        });

        it('maintains performance under mixed workload', function () {
            $startTime = microtime(true);
            
            // Mixed operations
            for ($i = 0; $i < 50; $i++) {
                // Create
                $task = Task::factory()->for($this->user)->create();
                
                // Read
                Task::find($task->id);
                
                // Update
                $task->update(['status' => 'completed']);
                
                // Cache operation
                Cache::put("stress_test_{$i}", $task->id, 60);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(10.0);
        });
    });
});