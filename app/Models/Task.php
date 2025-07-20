<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Translatable\HasTranslations;

class Task extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'due_date',
        'parent_id',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'datetime',
        'name' => 'array',
        'description' => 'array',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
        'description',
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
     * Task status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Task priority constants.
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Get all available task statuses.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get all available task priorities.
     *
     * @return array<string>
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * Get the parent task that this task belongs to.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the subtasks for this task.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Get all subtasks recursively.
     */
    public function allSubtasks(): HasMany
    {
        return $this->subtasks()->with('allSubtasks');
    }

    /**
     * Get the user that owns the task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include tasks for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include tasks with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include tasks with a specific priority.
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include root tasks (no parent).
     */
    public function scopeRootTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include subtasks (has parent).
     */
    public function scopeSubtasks($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !$this->isCompleted();
    }

    /**
     * Check if the task has subtasks.
     */
    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    /**
     * Check if the task is a subtask.
     */
    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get the task's completion percentage based on subtasks.
     */
    public function getCompletionPercentage(): int
    {
        if (!$this->hasSubtasks()) {
            return $this->isCompleted() ? 100 : 0;
        }

        $subtasks = $this->subtasks;
        $completedSubtasks = $subtasks->where('status', self::STATUS_COMPLETED)->count();
        
        return $subtasks->count() > 0 ? (int) round(($completedSubtasks / $subtasks->count()) * 100) : 0;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent circular references when setting parent
        static::saving(function ($task) {
            if ($task->parent_id && $task->parent_id === $task->id) {
                throw new \InvalidArgumentException('A task cannot be its own parent.');
            }

            // Prevent deep nesting by checking if parent is already a subtask
            if ($task->parent_id) {
                $parent = static::find($task->parent_id);
                if ($parent && $parent->isSubtask()) {
                    throw new \InvalidArgumentException('Cannot create subtask of a subtask. Maximum nesting level is 2.');
                }
            }
        });
    }
}
