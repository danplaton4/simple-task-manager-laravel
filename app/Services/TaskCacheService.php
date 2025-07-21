<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class TaskCacheService
{
    /**
     * Cache duration in seconds (5 minutes)
     */
    private const CACHE_DURATION = 300;

    /**
     * Cache duration for task details (10 minutes)
     */
    private const TASK_DETAILS_CACHE_DURATION = 600;

    /**
     * Cache duration for user statistics (15 minutes)
     */
    private const USER_STATS_CACHE_DURATION = 900;

    /**
     * Cache duration for frequently accessed data (30 minutes)
     */
    private const FREQUENT_ACCESS_CACHE_DURATION = 1800;

    /**
     * Get cached user tasks with optional filters
     *
     * @param int $userId
     * @param array $filters
     * @return Collection|null
     */
    public function getUserTasks(int $userId, array $filters = []): ?Collection
    {
        $cacheKey = $this->generateUserTasksCacheKey($userId, $filters);
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($userId, $filters) {
            $query = Task::where('user_id', $userId)
                ->with(['subtasks', 'parent']);

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }

            if (isset($filters['parent_id'])) {
                if ($filters['parent_id'] === 'null') {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $filters['parent_id']);
                }
            }

            if (isset($filters['due_date_from'])) {
                $query->where('due_date', '>=', $filters['due_date_from']);
            }

            if (isset($filters['due_date_to'])) {
                $query->where('due_date', '<=', $filters['due_date_to']);
            }

            return $query->orderBy('created_at', 'desc')->get();
        });
    }

    /**
     * Cache task details with relationships
     *
     * @param Task $task
     * @return void
     */
    public function cacheTaskDetails(Task $task): void
    {
        $cacheKey = $this->generateTaskDetailsCacheKey($task->id);
        
        // Load relationships if not already loaded
        if (!$task->relationLoaded('subtasks')) {
            $task->load('subtasks');
        }
        
        if (!$task->relationLoaded('parent')) {
            $task->load('parent');
        }

        Cache::put($cacheKey, $task, self::TASK_DETAILS_CACHE_DURATION);
    }

    /**
     * Get cached task details
     *
     * @param int $taskId
     * @return Task|null
     */
    public function getTaskDetails(int $taskId): ?Task
    {
        $cacheKey = $this->generateTaskDetailsCacheKey($taskId);
        
        return Cache::remember($cacheKey, self::TASK_DETAILS_CACHE_DURATION, function () use ($taskId) {
            return Task::with(['subtasks', 'parent', 'user'])->find($taskId);
        });
    }

    /**
     * Clear all cached data for a specific user
     *
     * @param int $userId
     * @return void
     */
    public function clearUserTasksCache(int $userId): void
    {
        $pattern = $this->generateUserTasksCachePattern($userId);
        $this->clearCacheByPattern($pattern);
    }

    /**
     * Clear cached task details
     *
     * @param int $taskId
     * @return void
     */
    public function clearTaskDetailsCache(int $taskId): void
    {
        $cacheKey = $this->generateTaskDetailsCacheKey($taskId);
        Cache::forget($cacheKey);
    }

    /**
     * Clear cache when task is updated
     *
     * @param Task $task
     * @return void
     */
    public function clearTaskCache(Task $task): void
    {
        // Clear task details cache
        $this->clearTaskDetailsCache($task->id);
        
        // Clear user tasks cache
        $this->clearUserTasksCache($task->user_id);
        
        // If task has a parent, clear parent's cache too
        if ($task->parent_id) {
            $this->clearTaskDetailsCache($task->parent_id);
            
            // Get parent task to clear its user's cache
            $parentTask = Task::find($task->parent_id);
            if ($parentTask) {
                $this->clearUserTasksCache($parentTask->user_id);
            }
        }
        
        // Clear cache for all subtasks
        $subtasks = Task::where('parent_id', $task->id)->get();
        foreach ($subtasks as $subtask) {
            $this->clearTaskDetailsCache($subtask->id);
            $this->clearUserTasksCache($subtask->user_id);
        }
    }

    /**
     * Cache user task statistics
     *
     * @param int $userId
     * @return array
     */
    public function getUserTaskStats(int $userId): array
    {
        $cacheKey = $this->generateUserStatsKey($userId);
        
        return Cache::remember($cacheKey, self::USER_STATS_CACHE_DURATION, function () use ($userId) {
            $tasks = Task::where('user_id', $userId);
            
            return [
                'total' => $tasks->count(),
                'pending' => $tasks->where('status', 'pending')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
                'cancelled' => $tasks->where('status', 'cancelled')->count(),
                'high_priority' => $tasks->where('priority', 'high')->count(),
                'urgent_priority' => $tasks->where('priority', 'urgent')->count(),
            ];
        });
    }

    /**
     * Clear user task statistics cache
     *
     * @param int $userId
     * @return void
     */
    public function clearUserStatsCache(int $userId): void
    {
        $cacheKey = $this->generateUserStatsKey($userId);
        Cache::forget($cacheKey);
    }

    /**
     * Generate cache key for user tasks
     *
     * @param int $userId
     * @param array $filters
     * @return string
     */
    private function generateUserTasksCacheKey(int $userId, array $filters = []): string
    {
        $filterHash = md5(serialize($filters));
        return "user:{$userId}:tasks:{$filterHash}";
    }

    /**
     * Generate cache key pattern for user tasks
     *
     * @param int $userId
     * @return string
     */
    private function generateUserTasksCachePattern(int $userId): string
    {
        return "user:{$userId}:tasks:*";
    }

    /**
     * Generate cache key for task details
     *
     * @param int $taskId
     * @return string
     */
    private function generateTaskDetailsCacheKey(int $taskId): string
    {
        return "task:{$taskId}:details";
    }

    /**
     * Generate cache key for user statistics
     *
     * @param int $userId
     * @return string
     */
    private function generateUserStatsKey(int $userId): string
    {
        return "user:{$userId}:stats";
    }

    /**
     * Clear cache entries matching a pattern
     *
     * @param string $pattern
     * @return void
     */
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            // Get cache prefix from config
            $prefix = config('database.redis.options.prefix', '');
            $fullPattern = $prefix . $pattern;
            
            // Get Redis connection for cache
            $redis = Redis::connection('cache');
            
            // Find keys matching the pattern
            $keys = $redis->keys($fullPattern);
            
            if (!empty($keys)) {
                // Remove prefix from keys before deleting
                $keysToDelete = array_map(function ($key) use ($prefix) {
                    return str_replace($prefix, '', $key);
                }, $keys);
                
                // Delete the keys
                foreach ($keysToDelete as $key) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't throw exception to avoid breaking the application
            \Log::error('Failed to clear cache by pattern: ' . $e->getMessage(), [
                'pattern' => $pattern,
                'exception' => $e
            ]);
        }
    }

    /**
     * Warm up cache for a user's tasks
     *
     * @param int $userId
     * @return void
     */
    public function warmUpUserCache(int $userId): void
    {
        // Cache basic user tasks
        $this->getUserTasks($userId);
        
        // Cache common filter combinations
        $commonFilters = [
            ['status' => 'pending'],
            ['status' => 'in_progress'],
            ['status' => 'completed'],
            ['priority' => 'high'],
            ['priority' => 'urgent'],
            ['parent_id' => 'null'], // Root tasks only
        ];
        
        foreach ($commonFilters as $filters) {
            $this->getUserTasks($userId, $filters);
        }
        
        // Cache user statistics
        $this->getUserTaskStats($userId);
    }

    /**
     * Check if cache is healthy
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'ok';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            return $retrieved === $testValue;
        } catch (\Exception $e) {
            return false;
        }
    }
}