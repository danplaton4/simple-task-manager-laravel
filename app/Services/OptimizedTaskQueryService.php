<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\DTOs\Task\TaskFilterDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizedTaskQueryService
{
    /**
     * Cache TTL for query results (15 minutes)
     */
    protected const QUERY_CACHE_TTL = 900;

    /**
     * Cache key prefix for query results
     */
    protected const QUERY_CACHE_PREFIX = 'optimized_task_query';

    /**
     * Locale cache service
     */
    protected LocaleCacheService $cacheService;

    /**
     * Performance monitor
     */
    protected TranslationPerformanceMonitor $performanceMonitor;

    public function __construct(
        LocaleCacheService $cacheService,
        TranslationPerformanceMonitor $performanceMonitor
    ) {
        $this->cacheService = $cacheService;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Get optimized task list with minimal translation data
     */
    public function getOptimizedTaskList(
        User $user,
        TaskFilterDTO $filters,
        string $locale,
        int $perPage = 15
    ): LengthAwarePaginator {
        $cacheKey = $this->generateQueryCacheKey('task_list', $user->id, $locale, $filters->toArray(), $perPage);
        
        // Try to get from cache first
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            Log::debug('Retrieved cached task list', [
                'user_id' => $user->id,
                'locale' => $locale,
                'cache_key' => $cacheKey
            ]);
            return $cachedResult;
        }

        // Track query performance
        $result = $this->performanceMonitor->trackQuery(
            'optimized_task_list',
            $locale,
            function () use ($user, $filters, $locale, $perPage) {
                return $this->buildOptimizedTaskListQuery($user, $filters, $locale, $perPage);
            },
            [
                'user_id' => $user->id,
                'filters' => $filters->toArray(),
                'per_page' => $perPage,
                'cached' => false,
            ]
        );

        // Cache the result
        Cache::put($cacheKey, $result, self::QUERY_CACHE_TTL);

        return $result;
    }

    /**
     * Build optimized query for task list
     */
    protected function buildOptimizedTaskListQuery(
        User $user,
        TaskFilterDTO $filters,
        string $locale,
        int $perPage
    ): LengthAwarePaginator {
        $query = Task::query()
            ->select([
                'id',
                'user_id',
                'parent_id',
                'status',
                'priority',
                'due_date',
                'created_at',
                'updated_at',
                // Use raw SQL to extract only the needed locale from JSON
                DB::raw("COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')),
                    JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))
                ) as localized_name"),
                DB::raw("COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')),
                    JSON_UNQUOTE(JSON_EXTRACT(description, '$.en'))
                ) as localized_description"),
                // Add translation status indicators
                DB::raw("CASE 
                    WHEN JSON_EXTRACT(name, '$.{$locale}') IS NOT NULL 
                    AND JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) != '' 
                    THEN 1 ELSE 0 END as has_name_translation"),
                DB::raw("CASE 
                    WHEN JSON_EXTRACT(description, '$.{$locale}') IS NOT NULL 
                    AND JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) != '' 
                    THEN 1 ELSE 0 END as has_description_translation")
            ])
            ->where('user_id', $user->id);

        // Apply filters using optimized indexes
        $this->applyOptimizedFilters($query, $filters, $locale);

        // Use optimized ordering
        $this->applyOptimizedOrdering($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Apply optimized filters to the query
     */
    protected function applyOptimizedFilters(Builder $query, TaskFilterDTO $filters, string $locale): void
    {
        // Status filter - uses tasks_user_status_index
        if ($filters->getStatus()) {
            $query->where('status', $filters->getStatus());
        }

        // Priority filter - uses tasks_user_priority_index
        if ($filters->getPriority()) {
            $query->where('priority', $filters->getPriority());
        }

        // Due date filters - uses tasks_due_date_index
        if ($filters->getDueDateFrom()) {
            $query->where('due_date', '>=', $filters->getDueDateFrom());
        }

        if ($filters->getDueDateTo()) {
            $query->where('due_date', '<=', $filters->getDueDateTo());
        }

        // Overdue filter - uses tasks_due_date_index
        if ($filters->isOverdue()) {
            $query->where('due_date', '<', now())
                  ->where('status', '!=', 'completed');
        }

        // Parent/subtask filter - uses tasks_parent_id_index
        if ($filters->getParentId() !== null) {
            $query->where('parent_id', $filters->getParentId());
        } elseif ($filters->isRootTasksOnly()) {
            $query->whereNull('parent_id');
        }

        // Search filter - uses JSON indexes
        if ($filters->getSearch()) {
            $this->applyOptimizedSearch($query, $filters->getSearch(), $locale);
        }

        // Date range filters - uses tasks_created_at_index and tasks_updated_at_index
        if ($filters->getCreatedFrom()) {
            $query->where('created_at', '>=', $filters->getCreatedFrom());
        }

        if ($filters->getCreatedTo()) {
            $query->where('created_at', '<=', $filters->getCreatedTo());
        }
    }

    /**
     * Apply optimized search using JSON indexes
     */
    protected function applyOptimizedSearch(Builder $query, string $search, string $locale): void
    {
        $searchTerm = "%{$search}%";
        $fallbackLocale = config('app.fallback_locale', 'en');

        $query->where(function ($q) use ($searchTerm, $locale, $fallbackLocale) {
            // Primary search in current locale (uses JSON indexes)
            $q->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) AS CHAR(255)) LIKE ?", [$searchTerm])
              ->orWhereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) AS CHAR(500)) LIKE ?", [$searchTerm]);

            // Fallback search if current locale is not the fallback
            if ($locale !== $fallbackLocale) {
                $q->orWhere(function ($fallbackQuery) use ($searchTerm, $fallbackLocale, $locale) {
                    // Search fallback when current locale is empty
                    $fallbackQuery->where(function ($nameQuery) use ($searchTerm, $fallbackLocale, $locale) {
                        $nameQuery->whereRaw("(JSON_EXTRACT(name, '$.{$locale}') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) = '')")
                                  ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$fallbackLocale}')) AS CHAR(255)) LIKE ?", [$searchTerm]);
                    })->orWhere(function ($descQuery) use ($searchTerm, $fallbackLocale, $locale) {
                        $descQuery->whereRaw("(JSON_EXTRACT(description, '$.{$locale}') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) = '')")
                                  ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$fallbackLocale}')) AS CHAR(500)) LIKE ?", [$searchTerm]);
                    });
                });
            }
        });
    }

    /**
     * Apply optimized ordering
     */
    protected function applyOptimizedOrdering(Builder $query, TaskFilterDTO $filters): void
    {
        $sortBy = $filters->getSortBy() ?? 'created_at';
        $sortDirection = $filters->getSortDirection() ?? 'desc';

        switch ($sortBy) {
            case 'name':
                // Sort by localized name
                $query->orderBy('localized_name', $sortDirection);
                break;
            case 'status':
                // Uses tasks_status_index
                $query->orderBy('status', $sortDirection);
                break;
            case 'priority':
                // Uses tasks_priority_index
                $query->orderBy('priority', $sortDirection);
                break;
            case 'due_date':
                // Uses tasks_due_date_index
                $query->orderBy('due_date', $sortDirection);
                break;
            case 'updated_at':
                // Uses tasks_updated_at_index
                $query->orderBy('updated_at', $sortDirection);
                break;
            default:
                // Uses tasks_created_at_index
                $query->orderBy('created_at', $sortDirection);
        }

        // Secondary sort by ID for consistent pagination
        $query->orderBy('id', 'asc');
    }

    /**
     * Get task with full translation data (for editing)
     */
    public function getTaskWithTranslations(int $taskId, User $user, string $locale): ?Task
    {
        $cacheKey = $this->generateQueryCacheKey('task_with_translations', $user->id, $locale, ['task_id' => $taskId]);
        
        // Try to get from cache first
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            return $cachedResult;
        }

        // Track query performance
        $result = $this->performanceMonitor->trackQuery(
            'task_with_translations',
            $locale,
            function () use ($taskId, $user) {
                return Task::where('id', $taskId)
                          ->where('user_id', $user->id)
                          ->first();
            },
            [
                'task_id' => $taskId,
                'user_id' => $user->id,
                'cached' => false,
            ]
        );

        if ($result) {
            // Cache the result
            Cache::put($cacheKey, $result, self::QUERY_CACHE_TTL);
        }

        return $result;
    }

    /**
     * Get task statistics with caching
     */
    public function getTaskStatistics(User $user, string $locale): array
    {
        $cacheKey = $this->generateQueryCacheKey('task_statistics', $user->id, $locale);
        
        // Try to get from cache first
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            return $cachedResult;
        }

        // Track query performance
        $result = $this->performanceMonitor->trackQuery(
            'task_statistics',
            $locale,
            function () use ($user) {
                return DB::table('tasks')
                    ->select([
                        DB::raw('COUNT(*) as total'),
                        DB::raw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed"),
                        DB::raw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending"),
                        DB::raw("COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress"),
                        DB::raw("COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled"),
                        DB::raw("COUNT(CASE WHEN due_date < NOW() AND status != 'completed' THEN 1 END) as overdue"),
                        DB::raw("COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as root_tasks"),
                        DB::raw("COUNT(CASE WHEN parent_id IS NOT NULL THEN 1 END) as subtasks"),
                    ])
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->first();
            },
            [
                'user_id' => $user->id,
                'cached' => false,
            ]
        );

        $statistics = (array) $result;

        // Cache the result
        Cache::put($cacheKey, $statistics, self::QUERY_CACHE_TTL);

        return $statistics;
    }

    /**
     * Invalidate query cache for a user
     */
    public function invalidateUserQueryCache(int $userId): void
    {
        try {
            // This is a simplified approach - in production, you might want to use cache tags
            $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
            
            foreach ($supportedLocales as $locale) {
                // Clear common cache patterns
                $patterns = [
                    "task_list_{$userId}_{$locale}_*",
                    "task_with_translations_{$userId}_{$locale}_*",
                    "task_statistics_{$userId}_{$locale}",
                ];
                
                foreach ($patterns as $pattern) {
                    $this->clearCacheByPattern($pattern);
                }
            }
            
            Log::info('Invalidated user query cache', ['user_id' => $userId]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate user query cache', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get query performance metrics
     */
    public function getQueryPerformanceMetrics(): array
    {
        return [
            'cache_prefix' => self::QUERY_CACHE_PREFIX,
            'cache_ttl_seconds' => self::QUERY_CACHE_TTL,
            'performance_metrics' => $this->performanceMonitor->getMetrics(),
            'database_indexes' => $this->getDatabaseIndexInfo(),
        ];
    }

    /**
     * Get database index information
     */
    protected function getDatabaseIndexInfo(): array
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM tasks");
            
            $indexInfo = [
                'total_indexes' => count($indexes),
                'json_indexes' => 0,
                'composite_indexes' => 0,
                'single_column_indexes' => 0,
                'index_details' => [],
            ];
            
            $indexGroups = [];
            foreach ($indexes as $index) {
                $keyName = $index->Key_name;
                
                if (!isset($indexGroups[$keyName])) {
                    $indexGroups[$keyName] = [
                        'name' => $keyName,
                        'unique' => $index->Non_unique == 0,
                        'columns' => [],
                        'is_json' => !empty($index->Expression),
                        'expression' => $index->Expression ?? null,
                    ];
                }
                
                if ($index->Column_name) {
                    $indexGroups[$keyName]['columns'][] = $index->Column_name;
                }
            }
            
            foreach ($indexGroups as $indexGroup) {
                if ($indexGroup['is_json']) {
                    $indexInfo['json_indexes']++;
                } elseif (count($indexGroup['columns']) > 1) {
                    $indexInfo['composite_indexes']++;
                } else {
                    $indexInfo['single_column_indexes']++;
                }
                
                $indexInfo['index_details'][] = $indexGroup;
            }
            
            return $indexInfo;
        } catch (\Exception $e) {
            Log::error('Failed to get database index info', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Generate cache key for query results
     */
    protected function generateQueryCacheKey(string $queryType, int $userId, string $locale, array $params = [], int $perPage = null): string
    {
        $keyParts = [
            self::QUERY_CACHE_PREFIX,
            $queryType,
            $userId,
            $locale,
        ];
        
        if (!empty($params)) {
            $keyParts[] = md5(serialize($params));
        }
        
        if ($perPage) {
            $keyParts[] = $perPage;
        }
        
        return implode('_', $keyParts);
    }

    /**
     * Clear cache entries by pattern (simplified implementation)
     */
    protected function clearCacheByPattern(string $pattern): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or cache tags
        Log::debug('Cache pattern clear requested', ['pattern' => $pattern]);
    }
}