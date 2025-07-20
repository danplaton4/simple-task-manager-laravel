<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendDigestEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'digest:send {type=daily} {--user-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send digest emails to users (daily or weekly)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $userId = $this->option('user-id');

        if (!in_array($type, ['daily', 'weekly'])) {
            $this->error('Invalid digest type. Use "daily" or "weekly".');
            return 1;
        }

        $this->info("Sending {$type} digest emails...");

        try {
            $users = $this->getEligibleUsers($type, $userId);
            $sentCount = 0;

            foreach ($users as $user) {
                if ($this->sendDigestEmail($user, $type)) {
                    $sentCount++;
                }
            }

            $this->info("Successfully sent {$sentCount} {$type} digest emails.");
            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to send digest emails: " . $e->getMessage());
            Log::error('Digest email command failed', [
                'type' => $type,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Get users eligible for digest emails
     */
    private function getEligibleUsers(string $type, ?int $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::query();

        if ($userId) {
            $query->where('id', $userId);
        }

        return $query->get()->filter(function ($user) use ($type) {
            return $user->wantsNotification($type . '_digest');
        });
    }

    /**
     * Send digest email to a user
     */
    private function sendDigestEmail(User $user, string $type): bool
    {
        try {
            $locale = $user->getPreferredLanguage();
            $digestData = $this->getDigestData($user, $type);

            if (empty($digestData['tasks'])) {
                $this->line("No tasks to include in {$type} digest for user {$user->name}");
                return false;
            }

            $subject = __("messages.email.{$type}_digest.subject", [
                'date' => $digestData['period_label']
            ], $locale);

            $content = $this->generateDigestContent($user, $digestData, $type, $locale);

            Mail::raw($content, function ($message) use ($user, $subject) {
                $message->to($user->email, $user->name)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Digest email sent', [
                'user_id' => $user->id,
                'type' => $type,
                'task_count' => count($digestData['tasks'])
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send digest email', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get digest data for a user
     */
    private function getDigestData(User $user, string $type): array
    {
        $now = Carbon::now();
        
        if ($type === 'daily') {
            $startDate = $now->copy()->subDay()->startOfDay();
            $endDate = $now->copy()->subDay()->endOfDay();
            $periodLabel = $startDate->format('Y-m-d');
        } else { // weekly
            $startDate = $now->copy()->subWeek()->startOfWeek();
            $endDate = $now->copy()->subWeek()->endOfWeek();
            $periodLabel = $startDate->format('M d') . ' - ' . $endDate->format('M d, Y');
        }

        // Get tasks created in the period
        $createdTasks = Task::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Get tasks completed in the period
        $completedTasks = Task::where('user_id', $user->id)
            ->where('status', Task::STATUS_COMPLETED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->get();

        // Get tasks due soon
        $tasksDueSoon = Task::where('user_id', $user->id)
            ->where('due_date', '>', $now)
            ->where('due_date', '<=', $now->copy()->addDays(3))
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->get();

        // Get overdue tasks
        $overdueTasks = Task::where('user_id', $user->id)
            ->where('due_date', '<', $now)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->get();

        return [
            'period_label' => $periodLabel,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'tasks' => [
                'created' => $createdTasks,
                'completed' => $completedTasks,
                'due_soon' => $tasksDueSoon,
                'overdue' => $overdueTasks,
            ],
            'stats' => [
                'created_count' => $createdTasks->count(),
                'completed_count' => $completedTasks->count(),
                'due_soon_count' => $tasksDueSoon->count(),
                'overdue_count' => $overdueTasks->count(),
                'total_active_tasks' => Task::where('user_id', $user->id)
                    ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
                    ->count(),
            ]
        ];
    }

    /**
     * Generate digest email content
     */
    private function generateDigestContent(User $user, array $digestData, string $type, string $locale): string
    {
        $content = __("messages.email.{$type}_digest.greeting", ['user_name' => $user->name], $locale) . "\n\n";
        
        $content .= __("messages.email.{$type}_digest.intro", [
            'period' => $digestData['period_label']
        ], $locale) . "\n\n";

        // Summary stats
        $content .= __("messages.email.{$type}_digest.summary", [], $locale) . "\n";
        $content .= "- " . __("messages.email.{$type}_digest.created_tasks", [
            'count' => $digestData['stats']['created_count']
        ], $locale) . "\n";
        $content .= "- " . __("messages.email.{$type}_digest.completed_tasks", [
            'count' => $digestData['stats']['completed_count']
        ], $locale) . "\n";
        $content .= "- " . __("messages.email.{$type}_digest.total_active", [
            'count' => $digestData['stats']['total_active_tasks']
        ], $locale) . "\n\n";

        // Tasks due soon
        if ($digestData['stats']['due_soon_count'] > 0) {
            $content .= __("messages.email.{$type}_digest.due_soon_section", [], $locale) . "\n";
            foreach ($digestData['tasks']['due_soon'] as $task) {
                $content .= "- {$task->getLocalizedName($locale)} (Due: {$task->due_date->setTimezone($user->getTimezone())->format('M d, Y')})\n";
            }
            $content .= "\n";
        }

        // Overdue tasks
        if ($digestData['stats']['overdue_count'] > 0) {
            $content .= __("messages.email.{$type}_digest.overdue_section", [], $locale) . "\n";
            foreach ($digestData['tasks']['overdue'] as $task) {
                $daysOverdue = Carbon::now()->diffInDays($task->due_date);
                $content .= "- {$task->getLocalizedName($locale)} ({$daysOverdue} days overdue)\n";
            }
            $content .= "\n";
        }

        // Recently completed tasks
        if ($digestData['stats']['completed_count'] > 0) {
            $content .= __("messages.email.{$type}_digest.completed_section", [], $locale) . "\n";
            foreach ($digestData['tasks']['completed']->take(5) as $task) {
                $content .= "- {$task->getLocalizedName($locale)}\n";
            }
            $content .= "\n";
        }

        $content .= __("messages.email.{$type}_digest.footer", [], $locale) . "\n";
        $content .= config('app.url') . "\n\n";
        $content .= __("messages.email.{$type}_digest.unsubscribe", [], $locale);

        return $content;
    }
}
