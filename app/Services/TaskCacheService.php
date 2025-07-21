<?php

namespace App\Services;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\DTOs\Task\TaskFilterDTO;
use Illuminate\Support\Facades\Log;

class TaskCacheService
{
    private const CACHE_DURATION = 300;

    private TaskRepositoryInterface $taskRepository;

    public function __construct(TaskRepositoryInterface $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function getUserTasks(int $userId, TaskFilterDTO $filters): ?Collection
    {
        $cacheKey = $this->generateUserTasksCacheKey($userId, $filters->getFiltersArray());
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($userId, $filters) {
            $user = new \App\Models\User(['id' => $userId]); // Temporary user object
            return $this->taskRepository->getTasksForUser($user, $filters);
        });
    }

    public function getTaskDetails(int $taskId): ?Task
    {
        $cacheKey = $this->generateTaskDetailsCacheKey($taskId);
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($taskId) {
            return $this->taskRepository->find($taskId);
        });
    }

    public function clearUserTasksCache(int $userId): void
    {
        $pattern = $this->generateUserTasksCachePattern($userId);
        $this->clearCacheByPattern($pattern);
    }

    public function clearTaskDetailsCache(int $taskId): void
    {
        $cacheKey = $this->generateTaskDetailsCacheKey($taskId);
        Cache::forget($cacheKey);
    }

    public function clearTaskCache(Task $task): void
    {
        $this->clearTaskDetailsCache($task->id);
        $this->clearUserTasksCache($task->user_id);
        
        if ($task->parent_id) {
            $this->clearTaskDetailsCache($task->parent_id);
        }
    }

    private function generateUserTasksCacheKey(int $userId, array $filters = []): string
    {
        $filterHash = md5(serialize($filters));
        return "user:{$userId}:tasks:{$filterHash}";
    }

    private function generateUserTasksCachePattern(int $userId): string
    {
        return "user:{$userId}:tasks:*";
    }

    private function generateTaskDetailsCacheKey(int $taskId): string
    {
        return "task:{$taskId}:details";
    }
    
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            $prefix = config('database.redis.options.prefix', '');
            $fullPattern = $prefix . $pattern;
            $redis = Redis::connection('cache');
            $keys = $redis->keys($fullPattern);
            
            if (!empty($keys)) {
                $keysToDelete = array_map(function ($key) use ($prefix) {
                    return str_replace($prefix, '', $key);
                }, $keys);
                
                foreach ($keysToDelete as $key) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear cache by pattern: ' . $e->getMessage());
        }
    }
}