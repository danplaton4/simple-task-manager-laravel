<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class ProcessTaskAnalyticsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $date = null,
        public ?int $userId = null
    ) {
        // Set queue connection and queue name
        $this->onConnection('redis');
        $this->onQueue('analytics');
        
        // Default to today if no date provided
        $this->date = $date ?? now()->format('Y-m-d');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $date = Carbon::parse($this->date);
            
            Log::info('Processing task analytics', [
                'date' => $this->date,
                'user_id' => $this->userId
            ]);

            if ($this->userId) {
                // Process analytics for specific user
                $this->processUserAnalytics($this->userId, $date);
            } else {
                // Process analytics for all users
                $this->processGlobalAnalytics($date);
            }

            Log::info('Task analytics processing completed', [
                'date' => $this->date,
                'user_id' => $this->userId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process task analytics', [
                'date' => $this->date,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process analytics for a specific user
     */
    private function processUserAnalytics(int $userId, Carbon $date): void
    {
        $user = User::find($userId);
        if (!$user) {
            Log::warning('User not found for analytics processing', ['user_id' => $userId]);
            return;
        }

        $dateStr = $date->format('Y-m-d');
        $redisKey = "analytics:user:{$userId}:daily:{$dateStr}";

        // Get task statistics for the user on the given date
        $stats = $this->calculateUserTaskStats($userId, $date);

        // Store in Redis with expiration (keep for 90 days)
        Redis::hmset($redisKey, $stats);
        Redis::expire($redisKey, 90 * 24 * 60 * 60); // 90 days

        // Update weekly and monthly aggregates
        $this->updateUserWeeklyStats($userId, $date, $stats);
        $this->updateUserMonthlyStats($userId, $date, $stats);

        Log::info('User analytics processed', [
            'user_id' => $userId,
            'date' => $dateStr,
            'stats' => $stats
        ]);
    }

    /**
     * Process global analytics for all users
     */
    private function processGlobalAnalytics(Carbon $date): void
    {
        $dateStr = $date->format('Y-m-d');
        $redisKey = "analytics:global:daily:{$dateStr}";

        // Get global task statistics
        $stats = $this->calculateGlobalTaskStats($date);

        // Store in Redis with expiration (keep for 1 year)
        Redis::hmset($redisKey, $stats);
        Redis::expire($redisKey, 365 * 24 * 60 * 60); // 1 year

        // Update weekly and monthly aggregates
        $this->updateGlobalWeeklyStats($date, $stats);
        $this->updateGlobalMonthlyStats($date, $stats);

        // Process analytics for each active user
        $activeUsers = User::whereHas('tasks', function ($query) use ($date) {
            $query->whereDate('updated_at', $date);
        })->get();

        foreach ($activeUsers as $user) {
            $this->processUserAnalytics($user->id, $date);
        }

        Log::info('Global analytics processed', [
            'date' => $dateStr,
            'stats' => $stats,
            'active_users' => $activeUsers->count()
        ]);
    }

    /**
     * Calculate task statistics for a specific user
     */
    private function calculateUserTaskStats(int $userId, Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Tasks created on this date
        $tasksCreated = Task::where('user_id', $userId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();

        // Tasks completed on this date
        $tasksCompleted = Task::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$startOfDay, $endOfDay])
            ->count();

        // Tasks updated on this date (excluding status changes to completed)
        $tasksUpdated = Task::where('user_id', $userId)
            ->where('status', '!=', 'completed')
            ->whereBetween('updated_at', [$startOfDay, $endOfDay])
            ->count();

        // Current task counts by status
        $currentStats = Task::where('user_id', $userId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Current task counts by priority
        $priorityStats = Task::where('user_id', $userId)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Overdue tasks
        $overdueTasks = Task::where('user_id', $userId)
            ->where('due_date', '<', $date)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        // Tasks due today
        $tasksDueToday = Task::where('user_id', $userId)
            ->whereDate('due_date', $date)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return [
            'tasks_created' => $tasksCreated,
            'tasks_completed' => $tasksCompleted,
            'tasks_updated' => $tasksUpdated,
            'total_tasks' => $currentStats['pending'] ?? 0 + $currentStats['in_progress'] ?? 0 + $currentStats['completed'] ?? 0 + $currentStats['cancelled'] ?? 0,
            'pending_tasks' => $currentStats['pending'] ?? 0,
            'in_progress_tasks' => $currentStats['in_progress'] ?? 0,
            'completed_tasks' => $currentStats['completed'] ?? 0,
            'cancelled_tasks' => $currentStats['cancelled'] ?? 0,
            'low_priority_tasks' => $priorityStats['low'] ?? 0,
            'medium_priority_tasks' => $priorityStats['medium'] ?? 0,
            'high_priority_tasks' => $priorityStats['high'] ?? 0,
            'urgent_priority_tasks' => $priorityStats['urgent'] ?? 0,
            'overdue_tasks' => $overdueTasks,
            'tasks_due_today' => $tasksDueToday,
            'completion_rate' => $tasksCreated > 0 ? round(($tasksCompleted / $tasksCreated) * 100, 2) : 0,
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate global task statistics
     */
    private function calculateGlobalTaskStats(Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Global task counts
        $tasksCreated = Task::whereBetween('created_at', [$startOfDay, $endOfDay])->count();
        $tasksCompleted = Task::where('status', 'completed')
            ->whereBetween('updated_at', [$startOfDay, $endOfDay])
            ->count();
        $tasksUpdated = Task::where('status', '!=', 'completed')
            ->whereBetween('updated_at', [$startOfDay, $endOfDay])
            ->count();

        // Active users (users who created or updated tasks)
        $activeUsers = User::whereHas('tasks', function ($query) use ($startOfDay, $endOfDay) {
            $query->whereBetween('created_at', [$startOfDay, $endOfDay])
                  ->orWhereBetween('updated_at', [$startOfDay, $endOfDay]);
        })->count();

        // Total users with tasks
        $totalUsersWithTasks = User::whereHas('tasks')->count();

        // Current global task counts
        $currentStats = Task::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Global overdue tasks
        $overdueTasks = Task::where('due_date', '<', $date)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return [
            'tasks_created' => $tasksCreated,
            'tasks_completed' => $tasksCompleted,
            'tasks_updated' => $tasksUpdated,
            'active_users' => $activeUsers,
            'total_users_with_tasks' => $totalUsersWithTasks,
            'total_tasks' => array_sum($currentStats),
            'pending_tasks' => $currentStats['pending'] ?? 0,
            'in_progress_tasks' => $currentStats['in_progress'] ?? 0,
            'completed_tasks' => $currentStats['completed'] ?? 0,
            'cancelled_tasks' => $currentStats['cancelled'] ?? 0,
            'overdue_tasks' => $overdueTasks,
            'completion_rate' => $tasksCreated > 0 ? round(($tasksCompleted / $tasksCreated) * 100, 2) : 0,
            'user_engagement_rate' => $totalUsersWithTasks > 0 ? round(($activeUsers / $totalUsersWithTasks) * 100, 2) : 0,
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Update weekly statistics for a user
     */
    private function updateUserWeeklyStats(int $userId, Carbon $date, array $dailyStats): void
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekKey = "analytics:user:{$userId}:weekly:{$weekStart->format('Y-m-d')}";

        // Get existing weekly stats or initialize
        $weeklyStats = Redis::hgetall($weekKey) ?: [];

        // Aggregate daily stats into weekly
        foreach (['tasks_created', 'tasks_completed', 'tasks_updated'] as $metric) {
            $weeklyStats[$metric] = ($weeklyStats[$metric] ?? 0) + $dailyStats[$metric];
        }

        // Update current counts (these are snapshots, not aggregates)
        foreach (['total_tasks', 'pending_tasks', 'in_progress_tasks', 'completed_tasks', 'cancelled_tasks', 'overdue_tasks'] as $metric) {
            $weeklyStats[$metric] = $dailyStats[$metric];
        }

        $weeklyStats['last_updated'] = now()->toISOString();

        Redis::hmset($weekKey, $weeklyStats);
        Redis::expire($weekKey, 365 * 24 * 60 * 60); // Keep for 1 year
    }

    /**
     * Update monthly statistics for a user
     */
    private function updateUserMonthlyStats(int $userId, Carbon $date, array $dailyStats): void
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthKey = "analytics:user:{$userId}:monthly:{$monthStart->format('Y-m')}";

        // Get existing monthly stats or initialize
        $monthlyStats = Redis::hgetall($monthKey) ?: [];

        // Aggregate daily stats into monthly
        foreach (['tasks_created', 'tasks_completed', 'tasks_updated'] as $metric) {
            $monthlyStats[$metric] = ($monthlyStats[$metric] ?? 0) + $dailyStats[$metric];
        }

        // Update current counts
        foreach (['total_tasks', 'pending_tasks', 'in_progress_tasks', 'completed_tasks', 'cancelled_tasks', 'overdue_tasks'] as $metric) {
            $monthlyStats[$metric] = $dailyStats[$metric];
        }

        $monthlyStats['last_updated'] = now()->toISOString();

        Redis::hmset($monthKey, $monthlyStats);
        Redis::expire($monthKey, 2 * 365 * 24 * 60 * 60); // Keep for 2 years
    }

    /**
     * Update global weekly statistics
     */
    private function updateGlobalWeeklyStats(Carbon $date, array $dailyStats): void
    {
        $weekStart = $date->copy()->startOfWeek();
        $weekKey = "analytics:global:weekly:{$weekStart->format('Y-m-d')}";

        $weeklyStats = Redis::hgetall($weekKey) ?: [];

        foreach (['tasks_created', 'tasks_completed', 'tasks_updated', 'active_users'] as $metric) {
            $weeklyStats[$metric] = ($weeklyStats[$metric] ?? 0) + $dailyStats[$metric];
        }

        foreach (['total_tasks', 'pending_tasks', 'in_progress_tasks', 'completed_tasks', 'cancelled_tasks', 'overdue_tasks', 'total_users_with_tasks'] as $metric) {
            $weeklyStats[$metric] = $dailyStats[$metric];
        }

        $weeklyStats['last_updated'] = now()->toISOString();

        Redis::hmset($weekKey, $weeklyStats);
        Redis::expire($weekKey, 2 * 365 * 24 * 60 * 60);
    }

    /**
     * Update global monthly statistics
     */
    private function updateGlobalMonthlyStats(Carbon $date, array $dailyStats): void
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthKey = "analytics:global:monthly:{$monthStart->format('Y-m')}";

        $monthlyStats = Redis::hgetall($monthKey) ?: [];

        foreach (['tasks_created', 'tasks_completed', 'tasks_updated', 'active_users'] as $metric) {
            $monthlyStats[$metric] = ($monthlyStats[$metric] ?? 0) + $dailyStats[$metric];
        }

        foreach (['total_tasks', 'pending_tasks', 'in_progress_tasks', 'completed_tasks', 'cancelled_tasks', 'overdue_tasks', 'total_users_with_tasks'] as $metric) {
            $monthlyStats[$metric] = $dailyStats[$metric];
        }

        $monthlyStats['last_updated'] = now()->toISOString();

        Redis::hmset($monthKey, $monthlyStats);
        Redis::expire($monthKey, 5 * 365 * 24 * 60 * 60); // Keep for 5 years
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTaskAnalyticsJob failed permanently', [
            'date' => $this->date,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
