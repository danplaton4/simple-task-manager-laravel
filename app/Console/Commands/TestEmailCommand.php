<?php

namespace App\Console\Commands;

use App\Mail\TaskCreatedMail;
use App\Mail\TaskUpdatedMail;
use App\Mail\TaskCompletedMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {type=created} {--locale=en}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email templates (created, updated, completed)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $locale = $this->option('locale');

        // Get a test user and task
        $user = User::first();
        $task = Task::with('parent')->first();

        if (!$user || !$task) {
            $this->error('No user or task found. Please create test data first.');
            return 1;
        }

        $this->info("Testing {$type} email template in {$locale} locale...");
        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Task: {$task->getLocalizedName($locale)}");

        try {
            switch ($type) {
                case 'created':
                    Mail::to($user->email)->send(new TaskCreatedMail($task, $user, $locale));
                    break;
                case 'updated':
                    $changes = [
                        'status' => ['old' => 'pending', 'new' => 'in_progress'],
                        'priority' => ['old' => 'medium', 'new' => 'high']
                    ];
                    Mail::to($user->email)->send(new TaskUpdatedMail($task, $user, $locale, null, $changes));
                    break;
                case 'completed':
                    // Temporarily set task as completed for testing
                    $originalStatus = $task->status;
                    $task->status = Task::STATUS_COMPLETED;
                    Mail::to($user->email)->send(new TaskCompletedMail($task, $user, $locale));
                    $task->status = $originalStatus;
                    break;
                default:
                    $this->error("Invalid email type. Use: created, updated, or completed");
                    return 1;
            }

            $this->info("Email sent successfully!");
            $this->info("Check your mail logs or MailHog at http://localhost:8025");
            
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
