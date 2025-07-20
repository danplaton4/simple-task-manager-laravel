<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\IntrospectionProcessor;

class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure custom log channels with proper rotation
        $this->configureLogChannels();
        
        // Set up log rotation schedule
        $this->scheduleLogRotation();
    }

    /**
     * Configure custom log channels
     */
    private function configureLogChannels(): void
    {
        // Ensure log directories exist
        $logPath = storage_path('logs');
        if (!file_exists($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // Configure performance monitoring
        if (config('app.env') === 'production') {
            // In production, log only slow operations
            config(['logging.performance_threshold' => 2.0]);
        } else {
            // In development, log all operations for debugging
            config(['logging.performance_threshold' => 0.1]);
        }
    }

    /**
     * Schedule log rotation
     */
    private function scheduleLogRotation(): void
    {
        // This would typically be handled by the scheduler
        // but we can set up some basic rotation logic here
        
        if (app()->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\CleanupLogsCommand::class,
            ]);
        }
    }
}
