<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

            Log::info('Task notification sent successfully', [
                'task_id' => $this->task->id,
                'action' => $this->action,
                'user_email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send task notification', [
                'task_id' => $this->task->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        $subject = "New Task Created: {$data['task']->name}";
        $this->sendEmailNotification($data, $subject, 'task_created');
    }

    /**
     * Send task updated notification
     */
    private function sendTaskUpdatedNotification(array $data): void
    {
        $subject = "Task Updated: {$data['task']->name}";
        $this->sendEmailNotification($data, $subject, 'task_updated');
    }

    /**
     * Send task completed notification
     */
    private function sendTaskCompletedNotification(array $data): void
    {
        $subject = "Task Completed: {$data['task']->name}";
        $this->sendEmailNotification($data, $subject, 'task_completed');
    }

    /**
     * Send task deleted notification
     */
    private function sendTaskDeletedNotification(array $data): void
    {
        $subject = "Task Deleted: {$data['task']->name}";
        $this->sendEmailNotification($data, $subject, 'task_deleted');
    }

    /**
     * Send task due soon notification
     */
    private function sendTaskDueSoonNotification(array $data): void
    {
        $subject = "Task Due Soon: {$data['task']->name}";
        $this->sendEmailNotification($data, $subject, 'task_due_soon');
    }

    /**
     * Send task overdue notification
     */
    private function sendTaskOverdueNotification(array $data): void
    {
        $subject = "Task Overdue: {$data['task']->name}";
        $this->sendEmailNotification($data, $subject, 'task_overdue');
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(array $data, string $subject, string $template): void
    {
        // For now, we'll use a simple mail approach
        // In a real application, you would create proper Mailable classes
        
        $emailContent = $this->generateEmailContent($data, $template);
        
        Mail::raw($emailContent, function ($message) use ($data, $subject) {
            $message->to($data['user']->email, $data['user']->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Generate email content based on template
     */
    private function generateEmailContent(array $data, string $template): string
    {
        $task = $data['task'];
        $user = $data['user'];
        $action = $data['action'];
        
        $content = "Hello {$user->name},\n\n";
        
        switch ($template) {
            case 'task_created':
                $content .= "A new task has been created:\n\n";
                break;
            case 'task_updated':
                $content .= "Your task has been updated:\n\n";
                break;
            case 'task_completed':
                $content .= "Congratulations! Your task has been completed:\n\n";
                break;
            case 'task_deleted':
                $content .= "Your task has been deleted:\n\n";
                break;
            case 'task_due_soon':
                $content .= "Reminder: Your task is due soon:\n\n";
                break;
            case 'task_overdue':
                $content .= "Alert: Your task is overdue:\n\n";
                break;
        }
        
        $content .= "Task: {$task->name}\n";
        
        if ($task->description) {
            $content .= "Description: {$task->description}\n";
        }
        
        $content .= "Status: {$task->status}\n";
        $content .= "Priority: {$task->priority}\n";
        
        if ($task->due_date) {
            $content .= "Due Date: {$task->due_date}\n";
        }
        
        if ($task->parent_id) {
            $content .= "Parent Task: {$task->parent->name}\n";
        }
        
        $content .= "\nYou can view your tasks at: " . config('app.url') . "\n\n";
        $content .= "Best regards,\n";
        $content .= config('app.name') . " Team";
        
        return $content;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendTaskNotificationJob failed permanently', [
            'task_id' => $this->task->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
