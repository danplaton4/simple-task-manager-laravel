<?php

namespace App\Console\Commands;

use App\Services\PerformanceOptimizationService;
use Illuminate\Console\Command;

class OptimizePerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:optimize-performance 
                            {--report : Generate performance report}
                            {--database : Optimize database indexes}
                            {--redis : Optimize Redis memory}
                            {--cache : Clean up expired cache}
                            {--health : Check system health}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize application performance and generate reports';

    /**
     * Execute the console command.
     */
    public function handle(PerformanceOptimizationService $optimizationService): int
    {
        $this->info('Starting performance optimization...');

        if ($this->option('health')) {
            $this->info('Checking system health...');
            $health = $optimizationService->checkSystemHealth();
            $this->displayHealthReport($health);
            return 0;
        }

        if ($this->option('report')) {
            $this->info('Generating performance report...');
            $report = $optimizationService->generatePerformanceReport();
            $this->displayPerformanceReport($report);
            return 0;
        }

        $results = [];

        if ($this->option('database') || !$this->hasOptions()) {
            $this->info('Optimizing database indexes...');
            $results['database'] = $optimizationService->optimizeDatabaseIndexes();
        }

        if ($this->option('redis') || !$this->hasOptions()) {
            $this->info('Optimizing Redis memory...');
            $results['redis'] = $optimizationService->optimizeRedisMemory();
        }

        if ($this->option('cache') || !$this->hasOptions()) {
            $this->info('Cleaning up expired cache...');
            $results['cache'] = $optimizationService->cleanupExpiredCache();
        }

        // Display results
        foreach ($results as $category => $messages) {
            $this->info("\n" . ucfirst($category) . " Optimization Results:");
            foreach ($messages as $message) {
                if (strpos($message, 'WARNING') === 0) {
                    $this->warn($message);
                } elseif (strpos($message, 'Failed') !== false) {
                    $this->error($message);
                } else {
                    $this->line("  ✓ {$message}");
                }
            }
        }

        $this->info("\nPerformance optimization completed!");
        return 0;
    }

    /**
     * Check if any options are provided
     */
    private function hasOptions(): bool
    {
        return $this->option('database') || 
               $this->option('redis') || 
               $this->option('cache') || 
               $this->option('report') ||
               $this->option('health');
    }

    /**
     * Display performance report
     */
    private function displayPerformanceReport(array $report): void
    {
        $this->info("Performance Report - {$report['timestamp']}");
        $this->info(str_repeat('=', 60));

        foreach ($report as $category => $data) {
            if ($category === 'timestamp') {
                continue;
            }

            $this->info("\n" . ucfirst(str_replace('_', ' ', $category)) . ":");
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (strpos($item, 'WARNING') === 0) {
                        $this->warn("  {$item}");
                    } elseif (strpos($item, 'Failed') !== false) {
                        $this->error("  {$item}");
                    } else {
                        $this->line("  • {$item}");
                    }
                }
            }
        }
    }

    /**
     * Display health report
     */
    private function displayHealthReport(array $health): void
    {
        $this->info("System Health Report");
        $this->info(str_repeat('=', 40));

        foreach ($health as $component => $data) {
            if ($component === 'overall') {
                continue;
            }

            $status = $data['status'] ?? 'unknown';
            $responseTime = isset($data['response_time_ms']) ? " ({$data['response_time_ms']}ms)" : '';
            
            $statusColor = match($status) {
                'healthy' => 'info',
                'slow' => 'warn',
                'unhealthy' => 'error',
                default => 'line'
            };

            $this->$statusColor(ucfirst($component) . ": {$status}{$responseTime}");
        }

        // Overall status
        if (isset($health['overall'])) {
            $overall = $health['overall'];
            $this->info("\nOverall System Status: " . strtoupper($overall['status']));
            
            if (isset($overall['error'])) {
                $this->error("Error: " . $overall['error']);
            }
        }
    }
}