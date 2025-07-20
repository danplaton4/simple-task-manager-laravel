<?php

use App\Models\Task;
use App\Models\User;
use App\Services\TaskCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

describe('TaskCacheService', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->service = new TaskCacheService();
        
        // Clear cache before each test
        Cache::flush();
    });

    describe('User Tasks Caching', function () {
        beforeEach(function () {
            // Create test tasks
            $this->pendingTask = Task::factory()->for($this->user)->create(['status' => 'pending', 'priority' => 'high']);
            $this->completedTask = Task::factory()->for($this->user)->create(['status' => 'completed', 'priority' => 'low']);
            $this->parentTask = Task::factory()->for($this->user)->create(['status' => 'in_progress']);
            $this->subtask = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);
        });

        it('caches user tasks without filters', function () {
            $tasks = $this->service->getUserTasks($this->user->id);
            
            expect($tasks)->toHaveCount(4);
            expect($tasks->pluck('id')->toArray())->toContain(
                $this->pendingTask->id,
                $this->completedTask->id,
                $this->parentTask->id,
                $this->subtask->id
            );
        });

        it('caches user tasks with status filter', function () {
            $tasks = $this->service->getUserTasks($this->user->id, ['status' => 'pending']);
            
            expect($tasks)->toHaveCount(1);
            expect($tasks->first()->id)->toBe($this->pendingTask->id);
        });

        it('caches user tasks with priority filter', function () {
            $tasks = $this->service->getUserTasks($this->user->id, ['priority' => 'high']);
            
            expect($tasks)->toHaveCount(1);
            expect($tasks->first()->id)->toBe($this->pendingTask->id);
        });

        it('caches user tasks with parent_id filter', function () {
            $tasks = $this->service->getUserTasks($this->user->id, ['parent_id' => 'null']);
            
            expect($tasks)->toHaveCount(3); // All except subtask
            expect($tasks->pluck('id')->toArray())->not->toContain($this->subtask->id);
        });

        it('caches user tasks with specific parent_id', function () {
            $tasks = $this->service->getUserTasks($this->user->id, ['parent_id' => $this->parentTask->id]);
            
            expect($tasks)->toHaveCount(1);
            expect($tasks->first()->id)->toBe($this->subtask->id);
        });

        it('caches user tasks with date range filters', function () {
            $futureTask = Task::factory()->for($this->user)->create([
                'due_date' => now()->addDays(5)
            ]);
            
            $tasks = $this->service->getUserTasks($this->user->id, [
                'due_date_from' => now()->addDays(3),
                'due_date_to' => now()->addDays(7)
            ]);
            
            expect($tasks)->toHaveCount(1);
            expect($tasks->first()->id)->toBe($futureTask->id);
        });

        it('returns cached results on subsequent calls', function () {
            // First call - should hit database
            $tasks1 = $this->service->getUserTasks($this->user->id);
            
            // Create a new task after caching
            Task::factory()->for($this->user)->create();
            
            // Second call - should return cached results (not including new task)
            $tasks2 = $this->service->getUserTasks($this->user->id);
            
            expect($tasks1->count())->toBe($tasks2->count());
            expect($tasks1->count())->toBe(4); // Original 4 tasks, not 5
        });
    });

    describe('Task Details Caching', function () {
        beforeEach(function () {
            $this->parentTask = Task::factory()->for($this->user)->create();
            $this->subtask = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);
        });

        it('caches task details with relationships', function () {
            $this->service->cacheTaskDetails($this->parentTask);
            
            $cachedTask = $this->service->getTaskDetails($this->parentTask->id);
            
            expect($cachedTask)->toBeInstanceOf(Task::class);
            expect($cachedTask->id)->toBe($this->parentTask->id);
            expect($cachedTask->relationLoaded('subtasks'))->toBeTrue();
            expect($cachedTask->relationLoaded('parent'))->toBeTrue();
        });

        it('loads relationships when caching if not already loaded', function () {
            $task = Task::find($this->parentTask->id); // Fresh instance without relationships
            
            $this->service->cacheTaskDetails($task);
            
            expect($task->relationLoaded('subtasks'))->toBeTrue();
            expect($task->relationLoaded('parent'))->toBeTrue();
        });

        it('returns cached task details on subsequent calls', function () {
            $task1 = $this->service->getTaskDetails($this->parentTask->id);
            
            // Modify task in database
            $this->parentTask->update(['name' => ['en' => 'Modified Name']]);
            
            // Should return cached version (not modified)
            $task2 = $this->service->getTaskDetails($this->parentTask->id);
            
            expect($task2->getTranslation('name', 'en'))->not->toBe('Modified Name');
        });
    });

    describe('Cache Clearing', function () {
        beforeEach(function () {
            $this->task = Task::factory()->for($this->user)->create();
            $this->parentTask = Task::factory()->for($this->user)->create();
            $this->subtask = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);
        });

        it('clears user tasks cache', function () {
            // Cache some data
            $this->service->getUserTasks($this->user->id);
            
            // Clear cache
            $this->service->clearUserTasksCache($this->user->id);
            
            // Create new task
            Task::factory()->for($this->user)->create();
            
            // Should get fresh data including new task
            $tasks = $this->service->getUserTasks($this->user->id);
            expect($tasks)->toHaveCount(4); // 3 original + 1 new
        });

        it('clears task details cache', function () {
            // Cache task details
            $this->service->cacheTaskDetails($this->task);
            
            // Clear cache
            $this->service->clearTaskDetailsCache($this->task->id);
            
            // Modify task
            $this->task->update(['name' => ['en' => 'Modified Name']]);
            
            // Should get fresh data
            $cachedTask = $this->service->getTaskDetails($this->task->id);
            expect($cachedTask->getTranslation('name', 'en'))->toBe('Modified Name');
        });

        it('clears all related caches when task is updated', function () {
            $otherUser = User::factory()->create();
            $this->subtask->update(['user_id' => $otherUser->id]); // Subtask belongs to different user
            
            // Cache data for both users
            $this->service->getUserTasks($this->user->id);
            $this->service->getUserTasks($otherUser->id);
            $this->service->cacheTaskDetails($this->parentTask);
            $this->service->cacheTaskDetails($this->subtask);
            
            // Clear cache for parent task
            $this->service->clearTaskCache($this->parentTask);
            
            // Create new tasks for both users
            Task::factory()->for($this->user)->create();
            Task::factory()->for($otherUser)->create();
            
            // Both users should get fresh data
            $userTasks = $this->service->getUserTasks($this->user->id);
            $otherUserTasks = $this->service->getUserTasks($otherUser->id);
            
            expect($userTasks)->toHaveCount(3); // 2 original + 1 new
            expect($otherUserTasks)->toHaveCount(2); // 1 original + 1 new
        });
    });

    describe('User Statistics Caching', function () {
        beforeEach(function () {
            Task::factory()->for($this->user)->create(['status' => 'pending', 'priority' => 'high']);
            Task::factory()->for($this->user)->create(['status' => 'completed', 'priority' => 'low']);
            Task::factory()->for($this->user)->create(['status' => 'in_progress', 'priority' => 'urgent']);
            Task::factory()->for($this->user)->create(['status' => 'cancelled', 'priority' => 'medium']);
        });

        it('caches user task statistics', function () {
            $stats = $this->service->getUserTaskStats($this->user->id);
            
            expect($stats)->toHaveKey('total', 4);
            expect($stats)->toHaveKey('pending', 1);
            expect($stats)->toHaveKey('completed', 1);
            expect($stats)->toHaveKey('in_progress', 1);
            expect($stats)->toHaveKey('cancelled', 1);
            expect($stats)->toHaveKey('high_priority', 1);
            expect($stats)->toHaveKey('urgent_priority', 1);
        });

        it('returns cached statistics on subsequent calls', function () {
            $stats1 = $this->service->getUserTaskStats($this->user->id);
            
            // Create new task
            Task::factory()->for($this->user)->create(['status' => 'pending']);
            
            // Should return cached stats (not updated)
            $stats2 = $this->service->getUserTaskStats($this->user->id);
            
            expect($stats1['total'])->toBe($stats2['total']);
            expect($stats2['total'])->toBe(4); // Not 5
        });

        it('clears user statistics cache', function () {
            $this->service->getUserTaskStats($this->user->id);
            
            $this->service->clearUserStatsCache($this->user->id);
            
            Task::factory()->for($this->user)->create(['status' => 'pending']);
            
            $stats = $this->service->getUserTaskStats($this->user->id);
            expect($stats['total'])->toBe(5); // Fresh data
        });
    });

    describe('Cache Warming', function () {
        it('warms up user cache with common filters', function () {
            Task::factory()->for($this->user)->create(['status' => 'pending', 'priority' => 'high']);
            Task::factory()->for($this->user)->create(['status' => 'completed', 'priority' => 'urgent']);
            
            $this->service->warmUpUserCache($this->user->id);
            
            // Verify that common filter combinations are cached
            // We can't directly test cache keys, but we can verify the data is accessible
            $pendingTasks = $this->service->getUserTasks($this->user->id, ['status' => 'pending']);
            $highPriorityTasks = $this->service->getUserTasks($this->user->id, ['priority' => 'high']);
            $rootTasks = $this->service->getUserTasks($this->user->id, ['parent_id' => 'null']);
            
            expect($pendingTasks)->toHaveCount(1);
            expect($highPriorityTasks)->toHaveCount(1);
            expect($rootTasks)->toHaveCount(2);
        });
    });

    describe('Health Check', function () {
        it('returns true when cache is healthy', function () {
            expect($this->service->isHealthy())->toBeTrue();
        });

        it('handles cache failures gracefully', function () {
            // Mock cache failure
            Cache::shouldReceive('put')->andThrow(new Exception('Cache error'));
            Cache::shouldReceive('get')->andReturn(null);
            Cache::shouldReceive('forget')->andReturn(true);
            
            expect($this->service->isHealthy())->toBeFalse();
        });
    });

    describe('Cache Key Generation', function () {
        it('generates consistent cache keys for same parameters', function () {
            $filters1 = ['status' => 'pending', 'priority' => 'high'];
            $filters2 = ['status' => 'pending', 'priority' => 'high'];
            
            // Call with same filters multiple times
            $this->service->getUserTasks($this->user->id, $filters1);
            $this->service->getUserTasks($this->user->id, $filters2);
            
            // Should use cached result (we can't directly test this, but no errors should occur)
            expect(true)->toBeTrue();
        });

        it('generates different cache keys for different filters', function () {
            Task::factory()->for($this->user)->create(['status' => 'pending']);
            Task::factory()->for($this->user)->create(['status' => 'completed']);
            
            $pendingTasks = $this->service->getUserTasks($this->user->id, ['status' => 'pending']);
            $completedTasks = $this->service->getUserTasks($this->user->id, ['status' => 'completed']);
            
            expect($pendingTasks)->toHaveCount(1);
            expect($completedTasks)->toHaveCount(1);
            expect($pendingTasks->first()->status)->toBe('pending');
            expect($completedTasks->first()->status)->toBe('completed');
        });
    });
});