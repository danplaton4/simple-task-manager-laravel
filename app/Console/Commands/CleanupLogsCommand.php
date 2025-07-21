<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\Services\LoggingService;

class CleanupLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup 
                            {--days=30 : Number of days to keep logs}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old log files based on retention policies';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Starting log cleanup process...");
        $this->info("Retention period: {$days} days");

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files will be deleted");
        }

        $logPath = storage_path('logs');
        $cutoffDate = Carbon::now()->subDays($days);

        if (!File::exists($logPath)) {
            $this->error("Log directory does not exist: {$logPath}");
            return 1;
        }

        $logFiles = File::files($logPath);
        $filesToDelete = [];
        $totalSize = 0;

        foreach ($logFiles as $file) {
            $fileTime = Carbon::createFromTimestamp($file->getMTime());
            
            if ($fileTime->lt($cutoffDate)) {
                $filesToDelete[] = [
                    'path' => $file->getPathname(),
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified' => $fileTime->format('Y-m-d H:i:s'),
                ];
                $totalSize += $file->getSize();
            }
        }

        if (empty($filesToDelete)) {
            $this->info("No log files found older than {$days} days.");
            return 0;
        }

        $this->info("Found " . count($filesToDelete) . " files to delete:");
        $this->table(
            ['File', 'Size', 'Modified'],
            array_map(function ($file) {
                return [
                    $file['name'],
                    $this->formatBytes($file['size']),
                    $file['modified']
                ];
            }, $filesToDelete)
        );

        $this->info("Total space to be freed: " . $this->formatBytes($totalSize));

        if ($dryRun) {
            $this->info("DRY RUN: Would delete " . count($filesToDelete) . " files");
            return 0;
        }

        if (!$force && !$this->confirm('Do you want to proceed with deletion?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($filesToDelete as $file) {
            try {
                if (File::delete($file['path'])) {
                    $deletedCount++;
                    $deletedSize += $file['size'];
                    $this->line("Deleted: {$file['name']}");
                } else {
                    $this->error("Failed to delete: {$file['name']}");
                }
            } catch (\Exception $e) {
                $this->error("Error deleting {$file['name']}: " . $e->getMessage());
            }
        }

        LoggingService::logPerformance('log_cleanup', microtime(true), [
            'files_deleted' => $deletedCount,
            'space_freed_bytes' => $deletedSize,
            'retention_days' => $days,
        ]);

        $this->info("Cleanup completed!");
        $this->info("Deleted {$deletedCount} files, freed " . $this->formatBytes($deletedSize));

        return 0;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
