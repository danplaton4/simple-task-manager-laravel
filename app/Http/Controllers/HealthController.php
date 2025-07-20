<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use App\Services\LoggingService;
use App\Services\RedisMonitoringService;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => Carbon::now()->toISOString(),
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
        ]);
    }

    /**
     * Comprehensive health check for all services
     */
    public function detailed(): JsonResponse
    {
        $startTime = microtime(true);
        $checks = [];
        $overallStatus = 'healthy';

        // Database health check
        $checks['database'] = $this->checkDatabase();
        
        // Redis health check
        $checks['redis'] = $this->checkRedis();
        
        // Cache health check
        $checks['cache'] = $this->checkCache();
        
        // Queue health check
        $checks['queue'] = $this->checkQueue();
        
        // Storage health check
        $checks['storage'] = $this->checkStorage();
        
        // Memory usage check
        $checks['memory'] = $this->checkMemory();

        // Determine overall status
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $overallStatus = 'unhealthy';
                break;
            }
        }

        $duration = microtime(true) - $startTime;

        LoggingService::logPerformance('health_check', $duration, [
            'overall_status' => $overallStatus,
            'checks_count' => count($checks),
        ]);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => Carbon::now()->toISOString(),
            'duration_ms' => round($duration * 1000, 2),
            'checks' => $checks,
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test basic connectivity
            DB::connection()->getPdo();
            
            // Test a simple query
            $result = DB::select('SELECT 1 as test');
            
            $duration = microtime(true) - $startTime;
            
            return [
                'status' => 'healthy',
                'duration_ms' => round($duration * 1000, 2),
                'connection' => DB::connection()->getName(),
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'connection' => DB::connection()->getName(),
            ];
        }
    }

    /**
     * Check Redis connectivity and performance
     */
    private function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test Redis connectivity
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            Redis::set($testKey, $testValue, 'EX', 10);
            $retrieved = Redis::get($testKey);
            Redis::del($testKey);
            
            $duration = microtime(true) - $startTime;
            
            if ($retrieved !== $testValue) {
                throw new \Exception('Redis read/write test failed');
            }
            
            return [
                'status' => 'healthy',
                'duration_ms' => round($duration * 1000, 2),
                'connection' => 'default',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache system
     */
    private function checkCache(): array
    {
        try {
            $startTime = microtime(true);
            
            $testKey = 'health_check_cache_' . time();
            $testValue = 'cache_test_value';
            
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            $duration = microtime(true) - $startTime;
            
            if ($retrieved !== $testValue) {
                throw new \Exception('Cache read/write test failed');
            }
            
            return [
                'status' => 'healthy',
                'duration_ms' => round($duration * 1000, 2),
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Check queue system
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $size = Queue::size();
            
            return [
                'status' => 'healthy',
                'connection' => $connection,
                'pending_jobs' => $size,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'connection' => config('queue.default'),
            ];
        }
    }

    /**
     * Check storage system
     */
    private function checkStorage(): array
    {
        try {
            $storagePath = storage_path();
            $logsPath = storage_path('logs');
            
            $totalSpace = disk_total_space($storagePath);
            $freeSpace = disk_free_space($storagePath);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = round(($usedSpace / $totalSpace) * 100, 2);
            
            $status = $usagePercent > 90 ? 'warning' : 'healthy';
            if ($usagePercent > 95) {
                $status = 'unhealthy';
            }
            
            return [
                'status' => $status,
                'total_space_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'usage_percent' => $usagePercent,
                'logs_writable' => is_writable($logsPath),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usagePercent = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;
        
        $status = 'healthy';
        if ($usagePercent > 80) {
            $status = 'warning';
        }
        if ($usagePercent > 90) {
            $status = 'unhealthy';
        }
        
        return [
            'status' => $status,
            'current_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'limit_mb' => $memoryLimit > 0 ? round($memoryLimit / 1024 / 1024, 2) : 'unlimited',
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return 0; // Unlimited
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Redis monitoring endpoint
     */
    public function redis(): JsonResponse
    {
        $startTime = microtime(true);
        
        $stats = RedisMonitoringService::getComprehensiveStats();
        
        $duration = microtime(true) - $startTime;
        
        LoggingService::logPerformance('redis_monitoring', $duration);
        
        return response()->json([
            'timestamp' => Carbon::now()->toISOString(),
            'duration_ms' => round($duration * 1000, 2),
            'redis' => $stats,
        ]);
    }

    /**
     * Application metrics endpoint
     */
    public function metrics(): JsonResponse
    {
        $startTime = microtime(true);
        
        // Collect various application metrics
        $metrics = [
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => config('app.env'),
                'debug_mode' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'performance' => [
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status()['opcache_enabled'] ?? false,
            ],
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
        ];
        
        $duration = microtime(true) - $startTime;
        
        LoggingService::logPerformance('metrics_collection', $duration, [
            'metrics_count' => count($metrics, COUNT_RECURSIVE),
        ]);
        
        return response()->json([
            'timestamp' => Carbon::now()->toISOString(),
            'duration_ms' => round($duration * 1000, 2),
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            return [
                'connection_name' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'database_name' => $connection->getDatabaseName(),
                'server_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'client_version' => $pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            return [
                'default_driver' => config('cache.default'),
                'prefix' => config('cache.prefix'),
                'stores' => array_keys(config('cache.stores')),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue metrics
     */
    private function getQueueMetrics(): array
    {
        try {
            return [
                'default_connection' => config('queue.default'),
                'connections' => array_keys(config('queue.connections')),
                'failed_jobs_table' => config('queue.failed.table'),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
