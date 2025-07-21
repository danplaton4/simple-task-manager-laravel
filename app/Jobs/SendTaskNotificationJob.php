<?php

namespace App\Jobs;

use App\Mail\TaskCreatedMail;
use App\Mail\TaskUpdatedMail;
use App\Mail\TaskCompletedMail;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\LoggingService;

class SendTaskNotificationJob implements ShouldQueue
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
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Task $task,
        public string $action,
        public ?array $additionalData = null
    ) {
        // Set queue connection and queue name
        $this->onConnection('redis');
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        LoggingService::logQueueJob('started', self::class, [
            'task_id' => $this->task->id,
            'action' => $this->action,
        ]);
        
        try {
            // Load task relationships if not already loaded
            if (!$this->task->relationLoaded('user')) {
                $this->task->load('user');
            }

            $user = $this->task->user;
            
            if (!$user || !$user->email) {
                Log::warning('Cannot send task notification: User or email not found', [
                    'task_id' => $this->task->id,
                    'user_id' => $this->task->user_id
                ]);
                return;
            }

            // Prepare notification data
            $notificationData = [
                'task' => $this->task,
                'action' => $this->action,
                'user' => $user,
                'additional_data' => $this->additionalData,
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
            ];

            // Check if user wants to receive this type of notification
            if (!$user->wantsNotification('task_' . $this->action)) {
                Log::info('User has disabled this notification type', [
                    'task_id' => $this->task->id,
                    'user_id' => $user->id,
                    'action' => $this->action
                ]);
                return;
            }

            // Send notification based on action type
            switch ($this->action) {
                case 'created':
                    $this->sendTaskCreatedNotification($notificationData);
                    break;
                
                case 'updated':
                    $this->sendTaskUpdatedNotification($notificationData);
                    break;
                
                case 'completed':
                    $this->sendTaskCompletedNotification($notificationData);
                    break;
                
                case 'deleted':
                    $this->sendTaskDeletedNotification($notificationData);
                    break;
                
                case 'due_soon':
                    $this->sendTaskDueSoonNotification($notificationData);
                    break;
                
                case 'overdue':
                    $this->sendTaskOverdueNotification($notificationData);
                    break;
                
                default:
                    Log::warning('Unknown task notification action', [
                        'action' => $this->action,
                        'task_id' => $this->task->id
                    ]);
            }

            LoggingService::logQueueJob('completed', self::class, [
                'task_id' => $this->task->id,
                'action' => $this->action,
                'user_email' => $user->email,
            ]);

        } catch (\Exception $e) {
            LoggingService::logQueueJob('failed', self::class, [
                'task_id' => $this->task->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Send task created notification
     */
    private function sendTaskCreatedNotification(array $data): void
    {
        $task = $data['task'];
        $user = $data['user'];
        $locale = $user->getPreferredLanguage();
        $taskUrl = $this->generateTaskUrl($task);
        
        Mail::send(new TaskCreatedMail($task, $user, $locale, $taskUrl));
    }

    /**
     * Send task updated notification
     */
    private function sendTaskUpdatedNotification(array $data): void
    {
        $task = $data['task'];
        $user = $data['user'];
        $locale = $user->getPreferredLanguage();
        $taskUrl = $this->generateTaskUrl($task);
        $changes = $data['additional_data']['changes'] ?? [];
        
        Mail::send(new TaskUpdatedMail($task, $user, $locale, $taskUrl, $changes));
    }

    /**
     * Send task completed notification
     */
    private function sendTaskCompletedNotification(array $data): void
    {
        $task = $data['task'];
        $user = $data['user'];
        $locale = $user->getPreferredLanguage();
        $taskUrl = $this->generateTaskUrl($task);
        
        Mail::send(new TaskCompletedMail($task, $user, $locale, $taskUrl));
    }

    /**
     * Send task deleted notification
     */
    private function sendTaskDeletedNotification(array $data): void
    {
        // For deleted tasks, we'll use a simple notification for now
        // You could create a TaskDeletedMail class if needed
        $task = $data['task'];
        $user = $data['user'];
        $locale = $user->getPreferredLanguage();
        
        $subject = __('messages.email.task_deleted.subject', [
            'task_name' => $task->getLocalizedName($locale)
        ], $locale);
        
        $content = __('messages.email.task_deleted.content', [
            'user_name' => $user->name,
            'task_name' => $task->getLocalizedName($locale)
        ], $locale);
        
        Mail::raw($content, function ($message) use ($user, $subject) {
            $message->to($user->email, $user->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Send task due soon notification
     */
    private function sendTaskDueSoonNotification(array $data): void
    {
        // For due soon notifications, we'll use a simple notification for now
        // You could create a TaskDueSoonMail class if needed
        $task = $data['task'];
        $user = $data['user'];
        $locale = $user->getPreferredLanguage();
        
        $subject = __('messages.email.task_due_soon.subject', [
            'task_name' => $task->getLocalizedName($locale)
        ], $locale);
        
        $content = __('messages.email.task_due_soon.content', [
            'user_name' => $user->name,
            'task_name' => $task->getLocalizedName($locale),
            'due_date' => $task->due_date->setTimezone($user->getTimezone())->format('Y-m-d H:i T')
        ], $locale);
        
        Mail::raw($content, function ($message) use ($user, $subject) {
            $message->to($user->email, $user->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Send task overdue notification
     */
    private function sendTaskOverdueNotification(array $data): void
    {
        // For overdue notifications, we'll use a simple notification for now
        // You could create a TaskOverdueMail class if needed
        $task = $data['task'];
        $user = $data['user'];
        $locale = $user->getPreferredLanguage();
        
        $subject = __('messages.email.task_overdue.subject', [
            'task_name' => $task->getLocalizedName($locale)
        ], $locale);
        
        $content = __('messages.email.task_overdue.content', [
            'user_name' => $user->name,
            'task_name' => $task->getLocalizedName($locale),
            'due_date' => $task->due_date->setTimezone($user->getTimezone())->format('Y-m-d H:i T'),
            'days_overdue' => $data['additional_data']['days_overdue'] ?? 0
        ], $locale);
        
        Mail::raw($content, function ($message) use ($user, $subject) {
            $message->to($user->email, $user->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Generate task URL for email links
     */
    private function generateTaskUrl(Task $task): string
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        return "{$frontendUrl}/tasks/{$task->id}";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        LoggingService::logQueueJob('failed_permanently', self::class, [
            'task_id' => $this->task->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'max_attempts' => $this->tries,
        ]);
    }
}
