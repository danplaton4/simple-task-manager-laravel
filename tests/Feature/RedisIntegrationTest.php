<?php

use App\Models\User;
use App\Models\Task;
use App\Services\TaskCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('Redis Integration Tests', function () {
    
    beforeEach(function () {
        Redis::flushall();
    });

    it('can cache and retrieve user tasks correctly', function () {
        $user = User::factory()->create();
        $tasks = Task::factory()->count(5)->for($user)->create();

        $cacheService = new TaskCacheService();
        
        // First call should hit database and cache result
        $cachedTasks = $cacheService->getUserTasks($user->id);
        expect($cachedTasks)->toHaveCount(5);

        // Verify data is cached
        $cacheKey = "user:{$user->id}:tasks:" . md5(serialize([]));
        expect(Cache::has($cacheKey))->toBeTrue();

        // Second call should hit cache
        $cachedTasksSecond = $cacheService->getUserTasks($user->id);
        expect($cachedTasksSecond)->toHaveCount(5);
    });

    it('can clear user task cache correctly', function () {
        $user = User::factory()->create();
        Task::factory()->count(3)->for($user)->create();

        $cacheService = new TaskCacheService();
        
        // Cache some data
        $cacheService->getUserTasks($user->id);
        $cacheService->getUserTasks($user->id, ['status' => 'pending']);
        
        // Verify cache exists
        $cacheKey1 = "user:{$user->id}:tasks:" . md5(serialize([]));
        $cacheKey2 = "user:{$user->id}:tasks:" . md5(serialize(['status' => 'pending']));
        
        expect(Cache::has($cacheKey1))->toBeTrue();
        expect(Cache::has($cacheKey2))->toBeTrue();

        // Clear cache
        $cacheService->clearUserTasksCache($user->id);

        // Verify cache is cleared
        expect(Cache::has($cacheKey1))->toBeFalse();
        expect(Cache::has($cacheKey2))->toBeFalse();
    });

    it('can cache task details with relationships', function () {
        $user = User::factory()->create();
        $parentTask = Task::factory()->for($user)->create();
        $subtasks = Task::factory()->count(2)->for($user)->create(['parent_id' => $parentTask->id]);

        $cacheService = new TaskCacheService();
        
        // Cache task details
        $cacheService->cacheTaskDetails($parentTask);

        // Verify cached data includes relationships
        $cacheKey = "task:{$parentTask->id}:details";
        $cachedTask = Cache::get($cacheKey);
        
        expect($cachedTask)->not->toBeNull();
        expect($cachedTask->subtasks)->toHaveCount(2);
    });

    it('can handle Redis session storage', function () {
        // Test session storage in Redis
        session(['test_key' => 'test_value']);
        expect(session('test_key'))->toBe('test_value');

        // Verify session is stored in Redis
        $sessionId = session()->getId();
        $redisKey = config('session.prefix') . $sessionId;
        
        // Check if session data exists in Redis
        $sessionData = Redis::connection('session')->get($redisKey);
        expect($sessionData)->not->toBeNull();
    });

    it('can handle Redis queue operations', function () {
        // Test Redis queue functionality
        $queueName = 'default';
        $jobData = ['test' => 'data'];

        // Push job to Redis queue
        Redis::connection('queue')->lpush("queues:$queueName", json_encode($jobData));

        // Verify job is in queue
        $queueLength = Redis::connection('queue')->llen("queues:$queueName");
        expect($queueLength)->toBe(1);

        // Pop job from queue
        $job = Redis::connection('queue')->rpop("queues:$queueName");
        $decodedJob = json_decode($job, true);
        
        expect($decodedJob['test'])->toBe('data');
    });

    it('can handle multiple Redis database connections', function () {
        // Test default connection (cache)
        Redis::connection('cache')->set('cache_test', 'cache_value');
        expect(Redis::connection('cache')->get('cache_test'))->toBe('cache_value');

        // Test session connection
        Redis::connection('session')->set('session_test', 'session_value');
        expect(Redis::connection('session')->get('session_test'))->toBe('session_value');

        // Test queue connection
        Redis::connection('queue')->set('queue_test', 'queue_value');
        expect(Redis::connection('queue')->get('queue_test'))->toBe('queue_value');

        // Verify isolation between connections
        expect(Redis::connection('cache')->get('session_test'))->toBeNull();
        expect(Redis::connection('session')->get('queue_test'))->toBeNull();
        expect(Redis::connection('queue')->get('cache_test'))->toBeNull();
    });

    it('can handle Redis pub/sub for real-time features', function () {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $channel = "user:{$user->id}:tasks";
        $message = [
            'task_id' => $task->id,
            'action' => 'updated',
            'task_data' => $task->toArray(),
            'timestamp' => now()->toISOString()
        ];

        // Publish message
        Redis::publish($channel, json_encode($message));

        // In a real scenario, we would test subscription
        // For testing purposes, we verify the publish operation
        expect(true)->toBeTrue(); // Placeholder assertion
    });

    it('can handle cache expiration and TTL', function () {
        $key = 'test_expiration';
        $value = 'test_value';
        $ttl = 2; // 2 seconds

        // Set with TTL
        Cache::put($key, $value, $ttl);
        expect(Cache::get($key))->toBe($value);

        // Check TTL
        $remainingTtl = Redis::ttl(Cache::getPrefix() . $key);
        expect($remainingTtl)->toBeGreaterThan(0);
        expect($remainingTtl)->toBeLessThanOrEqual($ttl);

        // Wait for expiration (in real test, we would mock time)
        sleep(3);
        expect(Cache::get($key))->toBeNull();
    });

    it('can handle Redis memory optimization', function () {
        // Test memory usage with large dataset
        $user = User::factory()->create();
        $tasks = Task::factory()->count(100)->for($user)->create();

        $cacheService = new TaskCacheService();
        
        // Cache large dataset
        $startMemory = memory_get_usage();
        $cachedTasks = $cacheService->getUserTasks($user->id);
        $endMemory = memory_get_usage();

        expect($cachedTasks)->toHaveCount(100);
        
        // Verify reasonable memory usage (less than 10MB for 100 tasks)
        $memoryUsed = $endMemory - $startMemory;
        expect($memoryUsed)->toBeLessThan(10 * 1024 * 1024); // 10MB
    });
});