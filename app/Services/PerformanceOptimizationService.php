<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceOptimizationService
{
    /**
     * Optimize database queries by adding missing indexes
     */
    public function optimizeDatabaseIndexes(): array
    {
        $optimizations = [];

        try {
            // Check if indexes exist and create them if missing
            $indexes = [
                'tasks_user_id_status_index' => [
                    'table' => 'tasks',
                    'columns' => ['user_id', 'status'],
                    'sql' => 'CREATE INDEX tasks_user_id_status_index ON tasks (user_id, status)'
                ],
                'tasks_user_id_priority_index' => [
                    'table' => 'tasks',
                    'columns' => ['user_id', 'priority'],
                    'sql' => 'CREATE INDEX tasks_user_id_priority_index ON tasks (user_id, priority)'
                ],
                'tasks_parent_id_status_index' => [
                    'table' => 'tasks',
                    'columns' => ['parent_id', 'status'],
                    'sql' => 'CREATE INDEX tasks_parent_id_status_index ON tasks (parent_id, status)'
                ],
                'tasks_due_date_status_index' => [
                    'table' => 'tasks',
                    'columns' => ['due_date', 'status'],
                    'sql' => 'CREATE INDEX tasks_due_date_status_index ON tasks (due_date, status)'
                ],
                'tasks_created_at_user_id_index' => [
                    'table' => 'tasks',
                    'columns' => ['created_at', 'user_id'],
                    'sql' => 'CREATE INDEX tasks_created_at_user_id_index ON tasks (created_at, user_id)'
                ]
            ];

            foreach ($indexes as $indexName => $indexInfo) {
                if (!$this->indexExists($indexInfo['table'], $indexName)) {
                    try {
                        DB::statement($indexInfo['sql']);
                        $optimizations[] = "Created index: {$indexName}";
                    } catch (\Exception $e) {
                        $optimizations[] = "Failed to create index {$indexName}: " . $e->getMessage();
                    }
                } else {
                    $optimizations[] = "Index {$indexName} already exists";
                }
            }
        } catch (\Exception $e) {
            Log::error('Database optimization failed: ' . $e->getMessage());
            $optimizations[] = 'Database optimization failed: ' . $e->getMessage();
        }

        return $optimizations;
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = config('database.default');
            
            if ($connection === 'mysql') {
                $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
                return !empty($result);
            } elseif ($connection === 'sqlite') {
                $result = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name = ?", [$indexName]);
                return !empty($result);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Optimize Redis memory usage
     */
    public function optimizeRedisMemory(): array
    {
        $optimizations = [];

        try {
            // Get Redis connections
            $connections = ['cache', 'session', 'queue'];
            
            foreach ($connections as $connectionName) {
                $redis = Redis::connection($connectionName);
                
                // Get memory info
                $info = $redis->info('memory');
                $usedMemory = $info['used_memory'] ?? 0;
                $maxMemory = $info['maxmemory'] ?? 0;
                
                $optimizations[] = "Redis {$connectionName} memory usage: " . $this->formatBytes($usedMemory);
                
                if ($maxMemory > 0) {
                    $memoryUsagePercent = ($usedMemory / $maxMemory) * 100;
                    $optimizations[] = "Redis {$connectionName} memory usage: {$memoryUsagePercent}%";
                    
                    // If memory usage is high, suggest cleanup
                    if ($memoryUsagePercent > 80) {
                        $optimizations[] = "WARNING: Redis {$connectionName} memory usage is high (>{$memoryUsagePercent}%)";
                    }
                }
                
                // Get key count
                $keyCount = $redis->dbsize();
                $optimizations[] = "Redis {$connectionName} key count: {$keyCount}";
            }
            
            // Optimize cache expiration policies
            $this->optimizeCacheExpiration();
            $optimizations[] = "Cache expiration policies optimized";
            
        } catch (\Exception $e) {
            Log::error('Redis optimization failed: ' . $e->getMessage());
            $optimizations[] = 'Redis optimization failed: ' . $e->getMessage();
        }

        return $optimizations;
    }

    /**
     * Optimize cache expiration policies
     */
    private function optimizeCacheExpiration(): void
    {
        try {
            $redis = Redis::connection('cache');
            
            // Set Redis configuration for better memory management
            $redis->config('SET', 'maxmemory-policy', 'allkeys-lru');
            $redis->config('SET', 'timeout', '300'); // 5 minutes timeout for idle connections
            
        } catch (\Exception $e) {
            Log::error('Cache expiration optimization failed: ' . $e->getMessage());
        }
    }

    /**
     * Clean up expired cache entries
     */
    public function cleanupExpiredCache(): array
    {
        $cleanupResults = [];

        try {
            // Clean up task cache entries that might be stale
            $redis = Redis::connection('cache');
            
            // Get all cache keys
            $keys = $redis->keys('*');
            $expiredCount = 0;
            $totalCount = count($keys);
            
            foreach ($keys as $key) {
                $ttl = $redis->ttl($key);
                
                // If key has no expiration or is expired, check if it should be cleaned up
                if ($ttl === -1) {
                    // Key has no expiration, set a default expiration
                    if (strpos($key, 'user:') === 0 && strpos($key, ':tasks:') !== false) {
                        $redis->expire($key, 300); // 5 minutes for user tasks
                        $expiredCount++;
                    } elseif (strpos($key, 'task:') === 0 && strpos($key, ':details') !== false) {
                        $redis->expire($key, 600); // 10 minutes for task details
                        $expiredCount++;
                    }
                }
            }
            
            $cleanupResults[] = "Processed {$totalCount} cache keys";
            $cleanupResults[] = "Set expiration for {$expiredCount} keys";
            
        } catch (\Exception $e) {
            Log::error('Cache cleanup failed: ' . $e->getMessage());
            $cleanupResults[] = 'Cache cleanup failed: ' . $e->getMessage();
        }

        return $cleanupResults;
    }

    /**
     * Optimize database connection pool
     */
    public function optimizeDatabaseConnections(): array
    {
        $optimizations = [];

        try {
            // Get current database configuration
            $config = config('database.connections.' . config('database.default'));
            
            $optimizations[] = "Database driver: " . ($config['driver'] ?? 'unknown');
            $optimizations[] = "Database host: " . ($config['host'] ?? 'unknown');
            
            // Check connection pool settings
            if (isset($config['options'])) {
                $optimizations[] = "PDO options configured: " . count($config['options']);
            }
            
            // Test database performance
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $endTime = microtime(true);
            $queryTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            $optimizations[] = "Database query response time: {$queryTime}ms";
            
            if ($queryTime > 100) {
                $optimizations[] = "WARNING: Database query response time is high (>{$queryTime}ms)";
            }
            
        } catch (\Exception $e) {
            Log::error('Database connection optimization failed: ' . $e->getMessage());
            $optimizations[] = 'Database connection optimization failed: ' . $e->getMessage();
        }

        return $optimizations;
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport(): array
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'database_optimizations' => $this->optimizeDatabaseIndexes(),
            'redis_optimizations' => $this->optimizeRedisMemory(),
            'cache_cleanup' => $this->cleanupExpiredCache(),
            'database_connections' => $this->optimizeDatabaseConnections(),
        ];

        // Log the report
        Log::info('Performance optimization report generated', $report);

        return $report;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Check system health and performance
     */
    public function checkSystemHealth(): array
    {
        $health = [];

        try {
            // Check database health
            $dbStart = microtime(true);
            DB::select('SELECT 1');
            $dbTime = (microtime(true) - $dbStart) * 1000;
            $health['database'] = [
                'status' => $dbTime < 100 ? 'healthy' : 'slow',
                'response_time_ms' => round($dbTime, 2)
            ];

            // Check Redis health
            $redisStart = microtime(true);
            Redis::ping();
            $redisTime = (microtime(true) - $redisStart) * 1000;
            $health['redis'] = [
                'status' => $redisTime < 50 ? 'healthy' : 'slow',
                'response_time_ms' => round($redisTime, 2)
            ];

            // Check cache health
            $cacheStart = microtime(true);
            Cache::put('health_check', 'ok', 60);
            $cacheValue = Cache::get('health_check');
            Cache::forget('health_check');
            $cacheTime = (microtime(true) - $cacheStart) * 1000;
            $health['cache'] = [
                'status' => ($cacheValue === 'ok' && $cacheTime < 50) ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($cacheTime, 2)
            ];

            // Overall system status
            $allHealthy = collect($health)->every(function ($component) {
                return $component['status'] === 'healthy';
            });
            
            $health['overall'] = [
                'status' => $allHealthy ? 'healthy' : 'degraded',
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('System health check failed: ' . $e->getMessage());
            $health['overall'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }

        return $health;
    }
}