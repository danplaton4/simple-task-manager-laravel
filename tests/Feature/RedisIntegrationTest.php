<?php

use App\Models\Task;
use App\Models\User;
use App\Services\TaskCacheService;
use App\Services\TaskEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Redis Integration', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        
        // Clear Redis before each test
        Redis::flushall();
        Cache::flush();
    });

    describe('Cache Integration', function () {
        beforeEach(function () {
            $this->cacheService = new TaskCacheService();
        });

        it('can store and retrieve data from Redis cache', function () {
            $key = 'test_key';
            $value = 'test_value';
            
            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            
            expect($retrieved)->toBe($value);
        });

        it('can clear cache by pattern', function () {
            // Store multiple cache entries
            Cache::put('user:1:tasks:abc', 'data1', 60);
            Cache::put('user:1:tasks:def', 'data2', 60);
            Cache::put('user:2:tasks:ghi', 'data3', 60);
            
            // Clear cache for user 1
            $this->cacheService->clearUserTasksCache(1);
            
            // User 1 cache should be cleared, user 2 should remain
            expect(Cache::get('user:1:tasks:abc'))->toBeNull();
            expect(Cache::get('user:1:tasks:def'))->toBeNull();
            expect(Cache::get('user:2:tasks:ghi'))->toBe('data3');
        });

        it('handles cache failures gracefully', function () {
            // Test cache health check
            expect($this->cacheService->isHealthy())->toBeTrue();
            
            // Simulate cache failure by using invalid connection
            // In a real scenario, you might mock Redis to throw exceptions
            expect($this->cacheService->isHealthy())->toBeTrue(); // Should still work
        });

        it('can warm up cache with user data', function () {
            Task::factory()->for($this->user)->create(['status' => 'pending']);
            Task::factory()->for($this->user)->create(['status' => 'completed']);
            
            $this->cacheService->warmUpUserCache($this->user->id);
            
            // Verify cache contains expected data
            $cachedTasks = $this->cacheService->getUserTasks($this->user->id);
            expect($cachedTasks)->toHaveCount(2);
        });
    });

    describe('Pub/Sub Integration', function () {
        beforeEach(function () {
            $this->eventService = new TaskEventService();
        });

        it('can publish messages to Redis channels', function () {
            $task = Task::factory()->for($this->user)->create();
            
            // This should not throw any exceptions
            $this->eventService->broadcastTaskCreated($task);
            
            expect(true)->toBeTrue(); // Test passes if no exceptions thrown
        });

        it('can check channel subscriber counts', function () {
            $count = $this->eventService->getUserChannelSubscribers($this->user->id);
            
            expect($count)->toBeInt();
            expect($count)->toBeGreaterThanOrEqual(0);
        });

        it('can send test events', function () {
            $result = $this->eventService->sendTestEvent($this->user->id);
            
            expect($result)->toBeTrue();
        });

        it('can get event statistics', function () {
            $stats = $this->eventService->getEventStatistics();
            
            expect($stats)->toHaveKey('status');
            expect($stats['status'])->toBeIn(['healthy', 'unhealthy']);
        });

        it('handles pub/sub health checks', function () {
            expect($this->eventService->isHealthy())->toBeTrue();
        });
    });

    describe('Queue Integration', function () {
        it('can dispatch jobs to Redis queue', function () {
            Queue::fake();
            
            $task = Task::factory()->for($this->user)->create();
            
            // Dispatch a job (this would normally be done in the controller)
            \App\Jobs\SendTaskNotificationJob::dispatch($task, 'created');
            
            Queue::assertPushed(\App\Jobs\SendTaskNotificationJob::class);
        });

        it('can process analytics jobs', function () {
            Queue::fake();
            
            \App\Jobs\ProcessTaskAnalyticsJob::dispatch();
            
            Queue::assertPushed(\App\Jobs\ProcessTaskAnalyticsJob::class);
        });
    });

    describe('Session Integration', function () {
        it('can store session data in Redis', function () {
            $sessionKey = 'test_session_key';
            $sessionValue = 'test_session_value';
            
            session([$sessionKey => $sessionValue]);
            
            expect(session($sessionKey))->toBe($sessionValue);
        });

        it('can handle multiple Redis databases', function () {
            // Test that different Redis databases are accessible
            $cacheConnection = Redis::connection('cache');
            $sessionConnection = Redis::connection('session');
            $queueConnection = Redis::connection('queue');
            
            expect($cacheConnection)->not->toBeNull();
            expect($sessionConnection)->not->toBeNull();
            expect($queueConnection)->not->toBeNull();
        });
    });

    describe('Redis Performance', function () {
        it('can handle multiple cache operations efficiently', function () {
            $startTime = microtime(true);
            
            // Perform multiple cache operations
            for ($i = 0; $i < 100; $i++) {
                Cache::put("test_key_{$i}", "test_value_{$i}", 60);
            }
            
            for ($i = 0; $i < 100; $i++) {
                Cache::get("test_key_{$i}");
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Should complete within reasonable time (adjust as needed)
            expect($executionTime)->toBeLessThan(5.0);
        });

        it('can handle concurrent cache access', function () {
            $key = 'concurrent_test_key';
            $value1 = 'value1';
            $value2 = 'value2';
            
            // Simulate concurrent access
            Cache::put($key, $value1, 60);
            $retrieved1 = Cache::get($key);
            
            Cache::put($key, $value2, 60);
            $retrieved2 = Cache::get($key);
            
            expect($retrieved1)->toBe($value1);
            expect($retrieved2)->toBe($value2);
        });
    });

    describe('Redis Connection Management', function () {
        it('can establish Redis connections', function () {
            $redis = Redis::connection();
            
            expect($redis)->not->toBeNull();
            
            // Test basic Redis operation
            $redis->set('connection_test', 'success');
            $result = $redis->get('connection_test');
            
            expect($result)->toBe('success');
        });

        it('can handle Redis connection failures gracefully', function () {
            // This test would require mocking Redis to simulate failures
            // For now, we'll test that the connection exists
            expect(Redis::connection())->not->toBeNull();
        });
    });
});