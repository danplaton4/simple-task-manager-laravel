<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TranslationPerformanceMonitor
{
    /**
     * Performance metrics cache key
     */
    protected const METRICS_CACHE_KEY = 'translation_performance_metrics';
    
    /**
     * Cache TTL for metrics (5 minutes)
     */
    protected const METRICS_CACHE_TTL = 300;

    /**
     * Query performance thresholds (in milliseconds)
     */
    protected const SLOW_QUERY_THRESHOLD = 100;
    protected const VERY_SLOW_QUERY_THRESHOLD = 500;

    /**
     * Track translation query performance
     */
    public function trackQuery(string $queryType, string $locale, callable $query, array $context = []): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            // Execute the query
            $result = $query();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsage = $endMemory - $startMemory;
            
            // Log performance metrics
            $this->logQueryPerformance($queryType, $locale, $executionTime, $memoryUsage, $context);
            
            // Update performance metrics
            $this->updateMetrics($queryType, $locale, $executionTime, $memoryUsage);
            
            return $result;
            
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            
            // Log failed query
            $this->logQueryError($queryType, $locale, $executionTime, $e, $context);
            
            throw $e;
        }
    }

    /**
     * Log query performance metrics
     */
    protected function logQueryPerformance(string $queryType, string $locale, float $executionTime, int $memoryUsage, array $context): void
    {
        $logLevel = 'debug';
        $message = "Translation query executed";
        
        // Determine log level based on performance
        if ($executionTime > self::VERY_SLOW_QUERY_THRESHOLD) {
            $logLevel = 'warning';
            $message = "Very slow translation query detected";
        } elseif ($executionTime > self::SLOW_QUERY_THRESHOLD) {
            $logLevel = 'info';
            $message = "Slow translation query detected";
        }
        
        Log::$logLevel($message, [
            'query_type' => $queryType,
            'locale' => $locale,
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_bytes' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log query errors
     */
    protected function logQueryError(string $queryType, string $locale, float $executionTime, \Exception $e, array $context): void
    {
        Log::error('Translation query failed', [
            'query_type' => $queryType,
            'locale' => $locale,
            'execution_time_ms' => round($executionTime, 2),
            'error' => $e->getMessage(),
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Update performance metrics
     */
    protected function updateMetrics(string $queryType, string $locale, float $executionTime, int $memoryUsage): void
    {
        try {
            $metrics = Cache::get(self::METRICS_CACHE_KEY, []);
            
            $key = "{$queryType}_{$locale}";
            
            if (!isset($metrics[$key])) {
                $metrics[$key] = [
                    'query_type' => $queryType,
                    'locale' => $locale,
                    'total_queries' => 0,
                    'total_time_ms' => 0,
                    'total_memory_bytes' => 0,
                    'avg_time_ms' => 0,
                    'avg_memory_mb' => 0,
                    'min_time_ms' => PHP_FLOAT_MAX,
                    'max_time_ms' => 0,
                    'slow_queries' => 0,
                    'very_slow_queries' => 0,
                    'last_updated' => now()->toISOString(),
                ];
            }
            
            $current = &$metrics[$key];
            $current['total_queries']++;
            $current['total_time_ms'] += $executionTime;
            $current['total_memory_bytes'] += $memoryUsage;
            $current['avg_time_ms'] = $current['total_time_ms'] / $current['total_queries'];
            $current['avg_memory_mb'] = ($current['total_memory_bytes'] / $current['total_queries']) / 1024 / 1024;
            $current['min_time_ms'] = min($current['min_time_ms'], $executionTime);
            $current['max_time_ms'] = max($current['max_time_ms'], $executionTime);
            $current['last_updated'] = now()->toISOString();
            
            if ($executionTime > self::VERY_SLOW_QUERY_THRESHOLD) {
                $current['very_slow_queries']++;
            } elseif ($executionTime > self::SLOW_QUERY_THRESHOLD) {
                $current['slow_queries']++;
            }
            
            Cache::put(self::METRICS_CACHE_KEY, $metrics, self::METRICS_CACHE_TTL);
            
        } catch (\Exception $e) {
            Log::error('Failed to update translation performance metrics', [
                'error' => $e->getMessage(),
                'query_type' => $queryType,
                'locale' => $locale,
            ]);
        }
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(): array
    {
        try {
            $metrics = Cache::get(self::METRICS_CACHE_KEY, []);
            
            return [
                'metrics' => array_values($metrics),
                'summary' => $this->calculateSummary($metrics),
                'thresholds' => [
                    'slow_query_ms' => self::SLOW_QUERY_THRESHOLD,
                    'very_slow_query_ms' => self::VERY_SLOW_QUERY_THRESHOLD,
                ],
                'cache_info' => [
                    'cache_key' => self::METRICS_CACHE_KEY,
                    'cache_ttl_seconds' => self::METRICS_CACHE_TTL,
                    'last_updated' => now()->toISOString(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve translation performance metrics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Calculate summary statistics
     */
    protected function calculateSummary(array $metrics): array
    {
        if (empty($metrics)) {
            return [
                'total_queries' => 0,
                'avg_time_ms' => 0,
                'total_slow_queries' => 0,
                'total_very_slow_queries' => 0,
                'performance_score' => 100,
            ];
        }
        
        $totalQueries = array_sum(array_column($metrics, 'total_queries'));
        $totalTime = array_sum(array_column($metrics, 'total_time_ms'));
        $totalSlowQueries = array_sum(array_column($metrics, 'slow_queries'));
        $totalVerySlowQueries = array_sum(array_column($metrics, 'very_slow_queries'));
        
        $avgTime = $totalQueries > 0 ? $totalTime / $totalQueries : 0;
        
        // Calculate performance score (0-100, where 100 is best)
        $slowQueryRatio = $totalQueries > 0 ? ($totalSlowQueries + $totalVerySlowQueries) / $totalQueries : 0;
        $performanceScore = max(0, 100 - ($slowQueryRatio * 50) - min(50, $avgTime / 10));
        
        return [
            'total_queries' => $totalQueries,
            'avg_time_ms' => round($avgTime, 2),
            'total_slow_queries' => $totalSlowQueries,
            'total_very_slow_queries' => $totalVerySlowQueries,
            'slow_query_percentage' => $totalQueries > 0 ? round($slowQueryRatio * 100, 2) : 0,
            'performance_score' => round($performanceScore, 1),
            'query_types' => array_unique(array_column($metrics, 'query_type')),
            'locales' => array_unique(array_column($metrics, 'locale')),
        ];
    }

    /**
     * Clear performance metrics
     */
    public function clearMetrics(): void
    {
        try {
            Cache::forget(self::METRICS_CACHE_KEY);
            Log::info('Translation performance metrics cleared');
        } catch (\Exception $e) {
            Log::error('Failed to clear translation performance metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get database query statistics for translation-related queries
     */
    public function getDatabaseStats(): array
    {
        try {
            // Enable query logging temporarily
            DB::enableQueryLog();
            
            // Get some sample translation queries to analyze
            $stats = [
                'connection' => config('database.default'),
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
                'query_log_enabled' => true,
                'sample_queries' => [],
            ];
            
            // You could add more sophisticated database analysis here
            // For example, checking for proper indexes on JSON fields
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get database stats for translations', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Monitor cache hit rates for translation data
     */
    public function getCacheStats(): array
    {
        try {
            $cacheService = app(LocaleCacheService::class);
            
            return [
                'cache_driver' => config('cache.default'),
                'cache_metrics' => $cacheService->getCacheMetrics(),
                'redis_info' => $this->getRedisInfo(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats for translations', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get Redis connection information
     */
    protected function getRedisInfo(): array
    {
        try {
            if (config('cache.default') !== 'redis') {
                return ['status' => 'not_using_redis'];
            }
            
            $redis = Cache::getStore()->getRedis();
            $info = $redis->info();
            
            return [
                'status' => 'connected',
                'version' => $info['redis_version'] ?? 'unknown',
                'memory_usage' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate cache hit rate
     */
    protected function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
}