<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocaleCacheService
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    protected const CACHE_TTL = 3600;

    /**
     * Cache key prefixes
     */
    protected const USER_LOCALE_PREFIX = 'user_locale';
    protected const TASK_TRANSLATION_PREFIX = 'task_translation';
    protected const TASK_LIST_PREFIX = 'task_list';
    protected const TRANSLATION_STATUS_PREFIX = 'translation_status';

    /**
     * Cache user locale preference
     */
    public function cacheUserLocale(int $userId, string $locale): void
    {
        try {
            $key = $this->getUserLocaleKey($userId);
            Cache::put($key, $locale, self::CACHE_TTL);
            
            Log::info('Cached user locale preference', [
                'user_id' => $userId,
                'locale' => $locale,
                'cache_key' => $key
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache user locale preference', [
                'user_id' => $userId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached user locale preference
     */
    public function getCachedUserLocale(int $userId): ?string
    {
        try {
            $key = $this->getUserLocaleKey($userId);
            $locale = Cache::get($key);
            
            if ($locale) {
                Log::debug('Retrieved cached user locale', [
                    'user_id' => $userId,
                    'locale' => $locale
                ]);
            }
            
            return $locale;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached user locale', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cache task translation data for a specific locale
     */
    public function cacheTaskTranslation(int $taskId, string $locale, array $translationData): void
    {
        try {
            $key = $this->getTaskTranslationKey($taskId, $locale);
            Cache::put($key, $translationData, self::CACHE_TTL);
            
            Log::debug('Cached task translation', [
                'task_id' => $taskId,
                'locale' => $locale,
                'cache_key' => $key
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache task translation', [
                'task_id' => $taskId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached task translation data
     */
    public function getCachedTaskTranslation(int $taskId, string $locale): ?array
    {
        try {
            $key = $this->getTaskTranslationKey($taskId, $locale);
            $data = Cache::get($key);
            
            if ($data) {
                Log::debug('Retrieved cached task translation', [
                    'task_id' => $taskId,
                    'locale' => $locale
                ]);
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached task translation', [
                'task_id' => $taskId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cache task list data for a specific user and locale
     */
    public function cacheTaskList(int $userId, string $locale, array $filters, array $taskData): void
    {
        try {
            $key = $this->getTaskListKey($userId, $locale, $filters);
            Cache::put($key, $taskData, self::CACHE_TTL / 2); // Shorter TTL for list data
            
            Log::debug('Cached task list', [
                'user_id' => $userId,
                'locale' => $locale,
                'filters' => $filters,
                'cache_key' => $key
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache task list', [
                'user_id' => $userId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached task list data
     */
    public function getCachedTaskList(int $userId, string $locale, array $filters): ?array
    {
        try {
            $key = $this->getTaskListKey($userId, $locale, $filters);
            $data = Cache::get($key);
            
            if ($data) {
                Log::debug('Retrieved cached task list', [
                    'user_id' => $userId,
                    'locale' => $locale,
                    'filters' => $filters
                ]);
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached task list', [
                'user_id' => $userId,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cache translation status for a task
     */
    public function cacheTranslationStatus(int $taskId, array $statusData): void
    {
        try {
            $key = $this->getTranslationStatusKey($taskId);
            Cache::put($key, $statusData, self::CACHE_TTL);
            
            Log::debug('Cached translation status', [
                'task_id' => $taskId,
                'cache_key' => $key
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache translation status', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cached translation status
     */
    public function getCachedTranslationStatus(int $taskId): ?array
    {
        try {
            $key = $this->getTranslationStatusKey($taskId);
            $data = Cache::get($key);
            
            if ($data) {
                Log::debug('Retrieved cached translation status', [
                    'task_id' => $taskId
                ]);
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cached translation status', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Invalidate all cache entries for a specific task
     */
    public function invalidateTaskCache(int $taskId): void
    {
        try {
            $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
            
            // Clear task translation cache for all locales
            foreach ($supportedLocales as $locale) {
                $translationKey = $this->getTaskTranslationKey($taskId, $locale);
                Cache::forget($translationKey);
            }
            
            // Clear translation status cache
            $statusKey = $this->getTranslationStatusKey($taskId);
            Cache::forget($statusKey);
            
            // Clear task list caches (this is more complex as we need to clear for all users)
            // For now, we'll use cache tags if available, or implement a more sophisticated approach
            $this->invalidateTaskListCaches($taskId);
            
            Log::info('Invalidated task cache', ['task_id' => $taskId]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate task cache', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Invalidate user-specific caches
     */
    public function invalidateUserCache(int $userId): void
    {
        try {
            // Clear user locale cache
            $localeKey = $this->getUserLocaleKey($userId);
            Cache::forget($localeKey);
            
            // Clear user's task list caches for all locales
            $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
            foreach ($supportedLocales as $locale) {
                // We need to clear all possible filter combinations
                // This is a simplified approach - in production, consider using cache tags
                $pattern = self::TASK_LIST_PREFIX . ":{$userId}:{$locale}:*";
                $this->clearCacheByPattern($pattern);
            }
            
            Log::info('Invalidated user cache', ['user_id' => $userId]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate user cache', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get performance metrics for cache operations
     */
    public function getCacheMetrics(): array
    {
        try {
            // This would typically integrate with your monitoring system
            return [
                'cache_driver' => config('cache.default'),
                'redis_connection' => config('cache.stores.redis.connection'),
                'cache_prefix' => config('cache.prefix'),
                'default_ttl' => self::CACHE_TTL,
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache metrics', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Generate cache key for user locale
     */
    protected function getUserLocaleKey(int $userId): string
    {
        return self::USER_LOCALE_PREFIX . ":{$userId}";
    }

    /**
     * Generate cache key for task translation
     */
    protected function getTaskTranslationKey(int $taskId, string $locale): string
    {
        return self::TASK_TRANSLATION_PREFIX . ":{$taskId}:{$locale}";
    }

    /**
     * Generate cache key for task list
     */
    protected function getTaskListKey(int $userId, string $locale, array $filters): string
    {
        $filterHash = md5(serialize($filters));
        return self::TASK_LIST_PREFIX . ":{$userId}:{$locale}:{$filterHash}";
    }

    /**
     * Generate cache key for translation status
     */
    protected function getTranslationStatusKey(int $taskId): string
    {
        return self::TRANSLATION_STATUS_PREFIX . ":{$taskId}";
    }

    /**
     * Invalidate task list caches (simplified approach)
     */
    protected function invalidateTaskListCaches(int $taskId): void
    {
        // In a production environment, you might want to use cache tags
        // or maintain a registry of cache keys to invalidate
        // For now, this is a placeholder for the logic
        
        // If using Redis, you could use SCAN to find and delete matching keys
        // This is a simplified implementation
        Log::debug('Task list cache invalidation triggered', ['task_id' => $taskId]);
    }

    /**
     * Clear cache entries by pattern (Redis-specific)
     */
    protected function clearCacheByPattern(string $pattern): void
    {
        // This would be implemented differently based on your cache driver
        // For Redis, you could use SCAN and DEL commands
        // This is a placeholder for the actual implementation
        Log::debug('Cache pattern clear requested', ['pattern' => $pattern]);
    }
}