<?php

namespace App\Services;

use App\Jobs\ProcessTaskAnalyticsJob;
use App\Jobs\SendTaskNotificationJob;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TaskJobDispatcher
{
    /**
     * Dispatch notification job for task creation
     *
     * @param Task $task
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchTaskCreatedNotification(Task $task, ?array $additionalData = null): void
    {
        try {
            SendTaskNotificationJob::dispatch($task, 'created', $additionalData);
            
            Log::info('Task created notification job dispatched', [
                'task_id' => $task->id,
                'user_id' => $task->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch task created notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch notification job for task update
     *
     * @param Task $task
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchTaskUpdatedNotification(Task $task, ?array $additionalData = null): void
    {
        try {
            SendTaskNotificationJob::dispatch($task, 'updated', $additionalData);
            
            Log::info('Task updated notification job dispatched', [
                'task_id' => $task->id,
                'user_id' => $task->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch task updated notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch notification job for task completion
     *
     * @param Task $task
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchTaskCompletedNotification(Task $task, ?array $additionalData = null): void
    {
        try {
            SendTaskNotificationJob::dispatch($task, 'completed', $additionalData);
            
            Log::info('Task completed notification job dispatched', [
                'task_id' => $task->id,
                'user_id' => $task->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch task completed notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch notification job for task deletion
     *
     * @param Task $task
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchTaskDeletedNotification(Task $task, ?array $additionalData = null): void
    {
        try {
            SendTaskNotificationJob::dispatch($task, 'deleted', $additionalData);
            
            Log::info('Task deleted notification job dispatched', [
                'task_id' => $task->id,
                'user_id' => $task->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch task deleted notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch notification job for tasks due soon
     *
     * @param Task $task
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchTaskDueSoonNotification(Task $task, ?array $additionalData = null): void
    {
        try {
            SendTaskNotificationJob::dispatch($task, 'due_soon', $additionalData);
            
            Log::info('Task due soon notification job dispatched', [
                'task_id' => $task->id,
                'user_id' => $task->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch task due soon notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch notification job for overdue tasks
     *
     * @param Task $task
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchTaskOverdueNotification(Task $task, ?array $additionalData = null): void
    {
        try {
            SendTaskNotificationJob::dispatch($task, 'overdue', $additionalData);
            
            Log::info('Task overdue notification job dispatched', [
                'task_id' => $task->id,
                'user_id' => $task->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch task overdue notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch analytics job for daily processing
     *
     * @param string|null $date
     * @param int|null $userId
     * @return void
     */
    public function dispatchDailyAnalytics(?string $date = null, ?int $userId = null): void
    {
        try {
            ProcessTaskAnalyticsJob::dispatch($date, $userId);
            
            Log::info('Daily analytics job dispatched', [
                'date' => $date ?? now()->format('Y-m-d'),
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch daily analytics job', [
                'date' => $date,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch analytics job for specific user
     *
     * @param int $userId
     * @param string|null $date
     * @return void
     */
    public function dispatchUserAnalytics(int $userId, ?string $date = null): void
    {
        try {
            ProcessTaskAnalyticsJob::dispatch($date, $userId);
            
            Log::info('User analytics job dispatched', [
                'user_id' => $userId,
                'date' => $date ?? now()->format('Y-m-d')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user analytics job', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch bulk notification jobs for multiple tasks
     *
     * @param array $tasks
     * @param string $action
     * @param array|null $additionalData
     * @return void
     */
    public function dispatchBulkNotifications(array $tasks, string $action, ?array $additionalData = null): void
    {
        foreach ($tasks as $task) {
            if (!$task instanceof Task) {
                continue;
            }

            switch ($action) {
                case 'created':
                    $this->dispatchTaskCreatedNotification($task, $additionalData);
                    break;
                case 'updated':
                    $this->dispatchTaskUpdatedNotification($task, $additionalData);
                    break;
                case 'completed':
                    $this->dispatchTaskCompletedNotification($task, $additionalData);
                    break;
                case 'deleted':
                    $this->dispatchTaskDeletedNotification($task, $additionalData);
                    break;
                case 'due_soon':
                    $this->dispatchTaskDueSoonNotification($task, $additionalData);
                    break;
                case 'overdue':
                    $this->dispatchTaskOverdueNotification($task, $additionalData);
                    break;
            }
        }

        Log::info('Bulk notifications dispatched', [
            'task_count' => count($tasks),
            'action' => $action
        ]);
    }

    /**
     * Dispatch jobs for tasks due soon (to be called by scheduler)
     *
     * @return void
     */
    public function dispatchDueSoonReminders(): void
    {
        try {
            // Get tasks due in the next 24 hours
            $tasksDueSoon = Task::where('due_date', '>', now())
                ->where('due_date', '<=', now()->addDay())
                ->whereIn('status', ['pending', 'in_progress'])
                ->get();

            foreach ($tasksDueSoon as $task) {
                $this->dispatchTaskDueSoonNotification($task, [
                    'hours_until_due' => now()->diffInHours($task->due_date)
                ]);
            }

            Log::info('Due soon reminders dispatched', [
                'task_count' => $tasksDueSoon->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch due soon reminders', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Dispatch jobs for overdue tasks (to be called by scheduler)
     *
     * @return void
     */
    public function dispatchOverdueNotifications(): void
    {
        try {
            // Get overdue tasks
            $overdueTasks = Task::where('due_date', '<', now())
                ->whereIn('status', ['pending', 'in_progress'])
                ->get();

            foreach ($overdueTasks as $task) {
                $this->dispatchTaskOverdueNotification($task, [
                    'days_overdue' => now()->diffInDays($task->due_date)
                ]);
            }

            Log::info('Overdue notifications dispatched', [
                'task_count' => $overdueTasks->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch overdue notifications', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get queue status information
     *
     * @return array
     */
    public function getQueueStatus(): array
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection('queue');
            
            return [
                'notifications_queue_size' => $redis->llen('queues:notifications'),
                'analytics_queue_size' => $redis->llen('queues:analytics'),
                'default_queue_size' => $redis->llen('queues:default'),
                'failed_jobs_count' => \DB::table('failed_jobs')->count(),
                'status' => 'healthy'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get queue status', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
}