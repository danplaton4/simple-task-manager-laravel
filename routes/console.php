<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule log cleanup to run daily at 2 AM
Schedule::command('logs:cleanup --days=30 --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule metrics collection every hour
Schedule::command('metrics:collect --store=redis --retention=7')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
