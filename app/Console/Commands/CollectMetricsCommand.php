<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Services\RedisMonitoringService;
use App\Services\LoggingService;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class CollectMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:collect 
                            {--store=redis : Where to store metrics (redis, log, database)}
                            {--retention=7 : Days to retain metrics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect and store application performance metrics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $store = $this->option('store');
        $retention = (int) $this->option('retention');

        $this->info('Collecting application metrics...');

        try {
            // Collect various metrics
            $metrics = $this->collectMetrics();
            
            // Store metrics based on the chosen storage method
            $this->storeMetrics($metrics, $store);
            
            // Clean up old metrics
            $this->cleanupOldMetrics($retention, $store);
            
            $duration = microtime(true) - $startTime;
            
            LoggingService::logPerformance('metrics_collection_command', $duration, [
                'metrics_count' => count($metrics, COUNT_RECURSIVE),
                'storage_method' => $store,
            ]);
            
            $this->info("Metrics collection completed in " . round($duration * 1000, 2) . "ms");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to collect metrics: " . $e->getMessage());
            
            LoggingService::logError($e, [
                'command' => 'metrics:collect',
                'storage_method' => $store,
            ]);
            
            return 1;
        }
    }

    /**
     * Collect comprehensive application metrics
     */
    private function collectMetrics(): array
    {
        $timestamp = Carbon::now();
        
        return [
            'timestamp' => $timestamp->toISOString(),
            'date' => $timestamp->format('Y-m-d'),
            'hour' => $timestamp->format('H'),
            
            // System metrics
            'system' => [
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'load_average' => $this->getLoadAverage(),
                'disk_usage' => $this->getDiskUsage(),
            ],
            
            // Database metrics
            'database' => $this->getDatabaseMetrics(),
            
            // Redis metrics
            'redis' => RedisMonitoringService::getComprehensiveStats(),
            
            // Application metrics
            'application' => [
                'total_users' => User::count(),
                'active_users_today' => User::whereDate('updated_at', $timestamp->format('Y-m-d'))->count(),
                'total_tasks' => Task::count(),
                'tasks_created_today' => Task::whereDate('created_at', $timestamp->format('Y-m-d'))->count(),
                'tasks_completed_today' => Task::where('status', 'completed')
                    ->whereDate('updated_at', $timestamp->format('Y-m-d'))
                    ->count(),
                'tasks_by_status' => $this->getTasksByStatus(),
                'tasks_by_priority' => $this->getTasksByPriority(),
            ],
            
            // Performance metrics
            'performance' => [
                'avg_response_time_ms' => $this->getAverageResponseTime(),
                'error_rate_percent' => $this->getErrorRate(),
                'cache_hit_rate_percent' => $this->getCacheHitRate(),
            ],
        ];
    }

    /**
     * Store metrics based on the chosen method
     */
    private function storeMetrics(array $metrics, string $store): void
    {
        switch ($store) {
            case 'redis':
                $this->storeInRedis($metrics);
                break;
            case 'log':
                $this->storeInLog($metrics);
                break;
            case 'database':
                $this->storeInDatabase($metrics);
                break;
            default:
                throw new \InvalidArgumentException("Unknown storage method: {$store}");
        }
    }

    /**
     * Store metrics in Redis
     */
    private function storeInRedis(array $metrics): void
    {
        $key = 'metrics:' . $metrics['date'] . ':' . $metrics['hour'];
        Redis::setex($key, 86400 * 7, json_encode($metrics)); // Store for 7 days
        
        // Also store in a daily summary
        $dailyKey = 'metrics:daily:' . $metrics['date'];
        $existing = Redis::get($dailyKey);
        $dailyData = $existing ? json_decode($existing, true) : [];
        $dailyData[$metrics['hour']] = $metrics;
        Redis::setex($dailyKey, 86400 * 30, json_encode($dailyData)); // Store for 30 days
        
        $this->info("Metrics stored in Redis with key: {$key}");
    }

    /**
     * Store metrics in log file
     */
    private function storeInLog(array $metrics): void
    {
        LoggingService::logPerformance('application_metrics', 0, $metrics);
        $this->info("Metrics stored in performance log");
    }

    /**
     * Store metrics in database (would require a metrics table)
     */
    private function storeInDatabase(array $metrics): void
    {
        // This would require creating a metrics table
        // For now, we'll just log it
        $this->warn("Database storage not implemented yet, storing in log instead");
        $this->storeInLog($metrics);
    }

    /**
     * Clean up old metrics
     */
    private function cleanupOldMetrics(int $retentionDays, string $store): void
    {
        if ($store === 'redis') {
            $cutoffDate = Carbon::now()->subDays($retentionDays);
            $pattern = 'metrics:' . $cutoffDate->format('Y-m-d') . ':*';
            
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del($keys);
                $this->info("Cleaned up " . count($keys) . " old metric entries");
            }
        }
    }

    /**
     * Get system load average (Unix systems only)
     */
    private function getLoadAverage(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2],
            ];
        }
        return null;
    }

    /**
     * Get disk usage information
     */
    private function getDiskUsage(): array
    {
        $path = storage_path();
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        
        return [
            'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'used_gb' => round(($totalSpace - $freeSpace) / 1024 / 1024 / 1024, 2),
            'usage_percent' => round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2),
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            // Get database size information
            $dbName = DB::connection()->getDatabaseName();
            $sizeQuery = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                COUNT(*) as table_count
                FROM information_schema.tables 
                WHERE table_schema = ?";
            
            $result = DB::select($sizeQuery, [$dbName]);
            
            return [
                'size_mb' => $result[0]->size_mb ?? 0,
                'table_count' => $result[0]->table_count ?? 0,
                'connection_count' => $this->getConnectionCount(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get database connection count
     */
    private function getConnectionCount(): int
    {
        try {
            $result = DB::select('SHOW STATUS WHERE Variable_name = "Threads_connected"');
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get tasks grouped by status
     */
    private function getTasksByStatus(): array
    {
        return Task::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get tasks grouped by priority
     */
    private function getTasksByPriority(): array
    {
        return Task::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();
    }

    /**
     * Get average response time (placeholder - would need actual implementation)
     */
    private function getAverageResponseTime(): float
    {
        // This would typically come from log analysis or APM tools
        return 0.0;
    }

    /**
     * Get error rate (placeholder - would need actual implementation)
     */
    private function getErrorRate(): float
    {
        // This would typically come from log analysis
        return 0.0;
    }

    /**
     * Get cache hit rate
     */
    private function getCacheHitRate(): float
    {
        try {
            $redisStats = RedisMonitoringService::getPerformanceStats();
            return $redisStats['hit_rate'] ?? 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
