<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Task $task,
        public User $user,
        public ?string $emailLocale = null,
        public ?string $taskUrl = null
    ) {
        $this->emailLocale = $emailLocale ?? $user->getPreferredLanguage();
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = __('messages.email.task_completed.subject', [
            'task_name' => $this->task->getLocalizedName($this->emailLocale)
        ], $this->emailLocale);

        return new Envelope(
            subject: $subject,
            to: [$this->user->email],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tasks.completed',
            with: [
                'task' => $this->task,
                'user' => $this->user,
                'locale' => $this->emailLocale,
                'taskUrl' => $this->taskUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Set the locale for this email
        app()->setLocale($this->emailLocale);
        
        return $this;
    }
}
