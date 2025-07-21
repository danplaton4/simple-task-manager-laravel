<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'preferred_language',
        'timezone',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Get the tasks for the user.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get only the root tasks (tasks without parent) for the user.
     */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id');
    }

    /**
     * Get the user's preferred language or default to English.
     */
    public function getPreferredLanguage(): string
    {
        return $this->preferred_language ?? 'en';
    }

    /**
     * Get the user's timezone or default to UTC.
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    /**
     * Get the user's notification preferences with defaults.
     */
    public function getNotificationPreferences(): array
    {
        $defaults = [
            'email_notifications' => true,
            'task_created' => true,
            'task_updated' => true,
            'task_completed' => true,
            'task_deleted' => false,
            'task_due_soon' => true,
            'task_overdue' => true,
            'daily_digest' => false,
            'weekly_digest' => false,
        ];

        return array_merge($defaults, $this->notification_preferences ?? []);
    }

    /**
     * Check if user wants to receive a specific type of notification.
     */
    public function wantsNotification(string $type): bool
    {
        $preferences = $this->getNotificationPreferences();
        
        // If email notifications are disabled, return false for all
        if (!$preferences['email_notifications']) {
            return false;
        }
        
        return $preferences[$type] ?? false;
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(array $preferences): void
    {
        $currentPreferences = $this->getNotificationPreferences();
        $updatedPreferences = array_merge($currentPreferences, $preferences);
        
        $this->update(['notification_preferences' => $updatedPreferences]);
    }

    /**
     * Enable all notifications.
     */
    public function enableAllNotifications(): void
    {
        $this->updateNotificationPreferences([
            'email_notifications' => true,
            'task_created' => true,
            'task_updated' => true,
            'task_completed' => true,
            'task_deleted' => true,
            'task_due_soon' => true,
            'task_overdue' => true,
            'daily_digest' => true,
            'weekly_digest' => true,
        ]);
    }

    /**
     * Disable all notifications.
     */
    public function disableAllNotifications(): void
    {
        $this->updateNotificationPreferences([
            'email_notifications' => false,
        ]);
    }

    /**
     * Determine if the user can create a task (domain logic).
     */
    public function canCreateTask(): bool
    {
        return !$this->trashed();
    }

    /**
     * Get task statistics for the user.
     * Returns array: total, completed, pending, in_progress, cancelled.
     */
    public function getTaskStatistics(): array
    {
        $tasks = $this->tasks();
        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'cancelled' => $tasks->where('status', 'cancelled')->count(),
        ];
    }
}
