<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class RedisMonitoringService
{
    /**
     * Get Redis server information
     */
    public static function getServerInfo(): array
    {
        try {
            $info = Redis::info();
            
            return [
                'status' => 'healthy',
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_seconds' => $info['uptime_in_seconds'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => self::calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get database-specific statistics
     */
    public static function getDatabaseStats(): array
    {
        try {
            $databases = [];
            $info = Redis::info('keyspace');
            
            foreach ($info as $key => $value) {
                if (strpos($key, 'db') === 0) {
                    $dbNumber = substr($key, 2);
                    $stats = self::parseDbStats($value);
                    $databases[$dbNumber] = $stats;
                }
            }
            
            return [
                'status' => 'healthy',
                'databases' => $databases,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get memory usage statistics
     */
    public static function getMemoryStats(): array
    {
        try {
            $info = Redis::info('memory');
            
            return [
                'status' => 'healthy',
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'used_memory_rss' => $info['used_memory_rss'] ?? 0,
                'used_memory_rss_human' => $info['used_memory_rss_human'] ?? '0B',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
                'used_memory_overhead' => $info['used_memory_overhead'] ?? 0,
                'used_memory_dataset' => $info['used_memory_dataset'] ?? 0,
                'mem_fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get performance statistics
     */
    public static function getPerformanceStats(): array
    {
        try {
            $info = Redis::info('stats');
            
            return [
                'status' => 'healthy',
                'total_connections_received' => $info['total_connections_received'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                'total_net_input_bytes' => $info['total_net_input_bytes'] ?? 0,
                'total_net_output_bytes' => $info['total_net_output_bytes'] ?? 0,
                'instantaneous_input_kbps' => $info['instantaneous_input_kbps'] ?? 0,
                'instantaneous_output_kbps' => $info['instantaneous_output_kbps'] ?? 0,
                'rejected_connections' => $info['rejected_connections'] ?? 0,
                'sync_full' => $info['sync_full'] ?? 0,
                'sync_partial_ok' => $info['sync_partial_ok'] ?? 0,
                'sync_partial_err' => $info['sync_partial_err'] ?? 0,
                'expired_keys' => $info['expired_keys'] ?? 0,
                'evicted_keys' => $info['evicted_keys'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => self::calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get slow log entries
     */
    public static function getSlowLog(int $count = 10): array
    {
        try {
            $slowLog = Redis::slowlog('get', $count);
            
            $entries = [];
            foreach ($slowLog as $entry) {
                $entries[] = [
                    'id' => $entry[0],
                    'timestamp' => Carbon::createFromTimestamp($entry[1])->toISOString(),
                    'duration_microseconds' => $entry[2],
                    'command' => implode(' ', array_slice($entry[3], 0, 3)) . (count($entry[3]) > 3 ? '...' : ''),
                    'client_ip' => $entry[4] ?? 'unknown',
                    'client_name' => $entry[5] ?? 'unknown',
                ];
            }
            
            return [
                'status' => 'healthy',
                'entries' => $entries,
                'count' => count($entries),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Monitor Redis connections by database
     */
    public static function getConnectionStats(): array
    {
        try {
            $connections = [
                'default' => self::testConnection('default'),
                'cache' => self::testConnection('cache'),
                'session' => self::testConnection('session'),
                'queue' => self::testConnection('queue'),
            ];
            
            return [
                'status' => 'healthy',
                'connections' => $connections,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test a specific Redis connection
     */
    private static function testConnection(string $connection): array
    {
        try {
            $startTime = microtime(true);
            $redis = Redis::connection($connection);
            $redis->ping();
            $duration = microtime(true) - $startTime;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => round($duration * 1000, 2),
                'database' => config("database.redis.{$connection}.database", 0),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private static function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Parse database statistics string
     */
    private static function parseDbStats(string $stats): array
    {
        $parts = explode(',', $stats);
        $result = [];
        
        foreach ($parts as $part) {
            [$key, $value] = explode('=', $part);
            $result[$key] = is_numeric($value) ? (int) $value : $value;
        }
        
        return $result;
    }

    /**
     * Get comprehensive Redis monitoring data
     */
    public static function getComprehensiveStats(): array
    {
        return [
            'server' => self::getServerInfo(),
            'databases' => self::getDatabaseStats(),
            'memory' => self::getMemoryStats(),
            'performance' => self::getPerformanceStats(),
            'connections' => self::getConnectionStats(),
            'slow_log' => self::getSlowLog(5),
            'timestamp' => Carbon::now()->toISOString(),
        ];
    }
}