<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MonitorTranslationPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:monitor-performance 
                            {--clear : Clear existing performance metrics}
                            {--export= : Export metrics to file}
                            {--format=json : Export format (json, csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and display translation query performance metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $performanceMonitor = app(\App\Services\TranslationPerformanceMonitor::class);
        $optimizedQueryService = app(\App\Services\OptimizedTaskQueryService::class);

        if ($this->option('clear')) {
            $performanceMonitor->clearMetrics();
            $this->info('Performance metrics cleared successfully.');
            return 0;
        }

        $this->info('Translation Performance Monitoring Report');
        $this->line('==========================================');

        // Get performance metrics
        $metrics = $performanceMonitor->getMetrics();
        $queryMetrics = $optimizedQueryService->getQueryPerformanceMetrics();

        if (empty($metrics['metrics'])) {
            $this->warn('No performance metrics available. Run some translation queries first.');
            return 0;
        }

        // Display summary
        $this->displaySummary($metrics['summary']);
        
        // Display detailed metrics
        $this->displayDetailedMetrics($metrics['metrics']);
        
        // Display database index information
        $this->displayIndexInformation($queryMetrics['database_indexes']);
        
        // Display cache information
        $this->displayCacheInformation($performanceMonitor->getCacheStats());

        // Export if requested
        if ($exportFile = $this->option('export')) {
            $this->exportMetrics($metrics, $exportFile, $this->option('format'));
        }

        return 0;
    }

    /**
     * Display performance summary
     */
    protected function displaySummary(array $summary): void
    {
        $this->newLine();
        $this->info('Performance Summary:');
        $this->line('-------------------');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', number_format($summary['total_queries'])],
                ['Average Time (ms)', number_format($summary['avg_time_ms'], 2)],
                ['Slow Queries', number_format($summary['total_slow_queries'])],
                ['Very Slow Queries', number_format($summary['total_very_slow_queries'])],
                ['Slow Query %', $summary['slow_query_percentage'] . '%'],
                ['Performance Score', $summary['performance_score'] . '/100'],
            ]
        );

        // Performance score interpretation
        $score = $summary['performance_score'];
        if ($score >= 90) {
            $this->info('✅ Excellent performance!');
        } elseif ($score >= 70) {
            $this->comment('⚠️  Good performance, some room for improvement.');
        } elseif ($score >= 50) {
            $this->warn('⚠️  Moderate performance, optimization recommended.');
        } else {
            $this->error('❌ Poor performance, immediate optimization required!');
        }
    }

    /**
     * Display detailed metrics
     */
    protected function displayDetailedMetrics(array $metrics): void
    {
        $this->newLine();
        $this->info('Detailed Metrics by Query Type and Locale:');
        $this->line('------------------------------------------');

        $tableData = [];
        foreach ($metrics as $metric) {
            $tableData[] = [
                $metric['query_type'],
                $metric['locale'],
                number_format($metric['total_queries']),
                number_format($metric['avg_time_ms'], 2),
                number_format($metric['min_time_ms'], 2),
                number_format($metric['max_time_ms'], 2),
                number_format($metric['slow_queries']),
                number_format($metric['very_slow_queries']),
                number_format($metric['avg_memory_mb'], 2),
            ];
        }

        $this->table(
            ['Query Type', 'Locale', 'Total', 'Avg (ms)', 'Min (ms)', 'Max (ms)', 'Slow', 'V.Slow', 'Mem (MB)'],
            $tableData
        );
    }

    /**
     * Display database index information
     */
    protected function displayIndexInformation(array $indexInfo): void
    {
        if (empty($indexInfo)) {
            return;
        }

        $this->newLine();
        $this->info('Database Index Information:');
        $this->line('---------------------------');

        $this->table(
            ['Index Type', 'Count'],
            [
                ['Total Indexes', $indexInfo['total_indexes']],
                ['JSON Indexes', $indexInfo['json_indexes']],
                ['Composite Indexes', $indexInfo['composite_indexes']],
                ['Single Column Indexes', $indexInfo['single_column_indexes']],
            ]
        );

        // Show JSON indexes specifically
        $jsonIndexes = array_filter($indexInfo['index_details'], fn($idx) => $idx['is_json']);
        if (!empty($jsonIndexes)) {
            $this->newLine();
            $this->comment('JSON Translation Indexes:');
            foreach ($jsonIndexes as $index) {
                $this->line("  • {$index['name']}: {$index['expression']}");
            }
        }
    }

    /**
     * Display cache information
     */
    protected function displayCacheInformation(array $cacheStats): void
    {
        if (empty($cacheStats)) {
            return;
        }

        $this->newLine();
        $this->info('Cache Information:');
        $this->line('------------------');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Driver', $cacheStats['cache_driver']],
                ['Redis Status', $cacheStats['redis_info']['status'] ?? 'N/A'],
                ['Redis Version', $cacheStats['redis_info']['version'] ?? 'N/A'],
                ['Memory Usage', $cacheStats['redis_info']['memory_usage'] ?? 'N/A'],
                ['Hit Rate', ($cacheStats['redis_info']['hit_rate'] ?? 0) . '%'],
            ]
        );
    }

    /**
     * Export metrics to file
     */
    protected function exportMetrics(array $metrics, string $filename, string $format): void
    {
        try {
            $data = [
                'timestamp' => now()->toISOString(),
                'summary' => $metrics['summary'],
                'detailed_metrics' => $metrics['metrics'],
            ];

            switch ($format) {
                case 'csv':
                    $this->exportToCsv($data, $filename);
                    break;
                case 'json':
                default:
                    $this->exportToJson($data, $filename);
                    break;
            }

            $this->info("Metrics exported to: {$filename}");
        } catch (\Exception $e) {
            $this->error("Failed to export metrics: {$e->getMessage()}");
        }
    }

    /**
     * Export to JSON format
     */
    protected function exportToJson(array $data, string $filename): void
    {
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Export to CSV format
     */
    protected function exportToCsv(array $data, string $filename): void
    {
        $handle = fopen($filename, 'w');
        
        // Write headers
        fputcsv($handle, [
            'Query Type', 'Locale', 'Total Queries', 'Avg Time (ms)', 
            'Min Time (ms)', 'Max Time (ms)', 'Slow Queries', 'Very Slow Queries', 'Avg Memory (MB)'
        ]);
        
        // Write data
        foreach ($data['detailed_metrics'] as $metric) {
            fputcsv($handle, [
                $metric['query_type'],
                $metric['locale'],
                $metric['total_queries'],
                $metric['avg_time_ms'],
                $metric['min_time_ms'],
                $metric['max_time_ms'],
                $metric['slow_queries'],
                $metric['very_slow_queries'],
                $metric['avg_memory_mb'],
            ]);
        }
        
        fclose($handle);
    }
}
