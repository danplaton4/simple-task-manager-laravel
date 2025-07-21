<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\DTOs\Task\TaskFilterDTO;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of TaskRepositoryInterface
 */
class EloquentTaskRepository extends BaseEloquentRepository implements TaskRepositoryInterface
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    /**
     * Create a new task from DTO
     */
    public function createFromDTO(CreateTaskDTO $dto, User $user): Task
    {
        return $this->model->create($dto->toModelData($user->id));
    }

    /**
     * Update a task from DTO
     */
    public function updateFromDTO(Task $task, UpdateTaskDTO $dto): Task
    {
        $data = $dto->toModelData();
        // Handle translations with Spatie methods
        if (isset($data['name'])) {
            $task->setTranslations('name', $data['name']);
            unset($data['name']);
        }
        if (isset($data['description'])) {
            $task->setTranslations('description', $data['description']);
            unset($data['description']);
        }
        $task->fill($data);
        $task->save();
        return $task->fresh();
    }

    /**
     * Find a task by ID and user
     */
    public function findByIdAndUser(int $id, User $user): ?Task
    {
        return $this->model->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Get tasks for a user with optional filtering
     */
    public function getTasksForUser(User $user, ?TaskFilterDTO $filter = null): Collection
    {
        $query = $this->model->where('user_id', $user->id);

        if ($filter) {
            $query = $this->applyFilters($query, $filter);
        }

        return $query->get();
    }

    /**
     * Get paginated tasks for a user with optional filtering
     */
    public function getPaginatedTasksForUser(User $user, ?TaskFilterDTO $filter = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('user_id', $user->id);

        if ($filter) {
            $query = $this->applyFilters($query, $filter);
            $perPage = $filter->perPage;
        }

        return $query->paginate($perPage);
    }

    /**
     * Get subtasks for a parent task
     */
    public function getSubtasks(Task $parentTask): Collection
    {
        return $this->model->where('parent_id', $parentTask->id)
            ->where('user_id', $parentTask->user_id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get tasks by status for a user
     */
    public function getTasksByStatus(User $user, string $status): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get overdue tasks for a user
     */
    public function getOverdueTasks(User $user): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where('due_date', '<', now())
            ->where('status', '!=', Task::STATUS_COMPLETED)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get completed tasks for a user
     */
    public function getCompletedTasks(User $user): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where('status', Task::STATUS_COMPLETED)
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Count tasks by status for a user
     */
    public function countTasksByStatus(User $user, string $status): int
    {
        return $this->model->where('user_id', $user->id)
            ->where('status', $status)
            ->count();
    }

    /**
     * Get tasks with relationships loaded
     */
    public function getTasksWithRelationships(User $user, array $relationships = ['parent', 'subtasks']): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->with($relationships)
            ->get();
    }

    /**
     * Get root tasks (tasks without parent) for a user
     */
    public function getRootTasks(User $user): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get tasks by priority for a user
     */
    public function getTasksByPriority(User $user, string $priority): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where('priority', $priority)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Search tasks by name or description
     */
    public function searchTasks(User $user, string $query): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where(function ($q) use ($query) {
                $q->whereJsonContains('name->en', $query)
                    ->orWhereJsonContains('description->en', $query)
                    ->orWhereJsonContains('name->de', $query)
                    ->orWhereJsonContains('description->de', $query)
                    ->orWhereJsonContains('name->fr', $query)
                    ->orWhereJsonContains('description->fr', $query);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get tasks due within a specific date range
     */
    public function getTasksDueInRange(User $user, \Carbon\Carbon $from, \Carbon\Carbon $to): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->whereBetween('due_date', [$from, $to])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get task statistics for a user
     */
    public function getTaskStatistics(User $user): array
    {
        $cacheKey = "user:{$user->id}:task_stats";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $baseQuery = $this->model->where('user_id', $user->id);

            return [
                'total' => $baseQuery->count(),
                'pending' => $baseQuery->where('status', Task::STATUS_PENDING)->count(),
                'in_progress' => $baseQuery->where('status', Task::STATUS_IN_PROGRESS)->count(),
                'completed' => $baseQuery->where('status', Task::STATUS_COMPLETED)->count(),
                'cancelled' => $baseQuery->where('status', Task::STATUS_CANCELLED)->count(),
                'overdue' => $baseQuery->where('due_date', '<', now())
                    ->where('status', '!=', Task::STATUS_COMPLETED)->count(),
                'due_today' => $baseQuery->whereDate('due_date', today())
                    ->where('status', '!=', Task::STATUS_COMPLETED)->count(),
                'due_this_week' => $baseQuery->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->where('status', '!=', Task::STATUS_COMPLETED)->count(),
                'root_tasks' => $baseQuery->whereNull('parent_id')->count(),
                'subtasks' => $baseQuery->whereNotNull('parent_id')->count(),
            ];
        });
    }

    /**
     * Clear task statistics cache for a user
     */
    public function clearTaskStatisticsCache(User $user): void
    {
        Cache::forget("user:{$user->id}:task_stats");
    }

    /**
     * Get tasks with caching
     */
    public function getCachedTasksForUser(User $user, TaskFilterDTO $filter): Collection
    {
        $cacheKey = $filter->getCacheKey($user->id);

        return Cache::remember($cacheKey, 300, function () use ($user, $filter) {
            return $this->getTasksForUser($user, $filter);
        });
    }

    /**
     * Clear task cache for a user
     */
    public function clearTaskCache(User $user): void
    {
        $pattern = "user:{$user->id}:tasks:*";

        // In a real implementation, you'd use Redis SCAN or similar
        // For now, we'll clear the statistics cache
        $this->clearTaskStatisticsCache($user);
    }

    /**
     * Bulk update task status
     */
    public function bulkUpdateStatus(User $user, array $taskIds, string $status): int
    {
        return $this->model->where('user_id', $user->id)
            ->whereIn('id', $taskIds)
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    /**
     * Bulk delete tasks
     */
    public function bulkDelete(User $user, array $taskIds): int
    {
        return $this->model->where('user_id', $user->id)
            ->whereIn('id', $taskIds)
            ->delete();
    }

    /**
     * Get tasks that need notification (due soon, overdue, etc.)
     */
    public function getTasksNeedingNotification(User $user): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Due today
                    $q->whereDate('due_date', today())
                        ->where('status', '!=', Task::STATUS_COMPLETED);
                })->orWhere(function ($q) {
                    // Overdue
                    $q->where('due_date', '<', now())
                        ->where('status', '!=', Task::STATUS_COMPLETED);
                });
            })
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, TaskFilterDTO $filter)
    {
        // Status filter
        if ($filter->status) {
            $query->where('status', $filter->status);
        }

        // Priority filter
        if ($filter->priority) {
            $query->where('priority', $filter->priority);
        }

        // Parent ID filter
        if ($filter->parentId !== null) {
            $query->where('parent_id', $filter->parentId);
        }

        // Hierarchy level filter
        if ($filter->isRootTasksOnly()) {
            $query->whereNull('parent_id');
        } elseif ($filter->isSubtasksOnly()) {
            $query->whereNotNull('parent_id');
        }

        // Date range filter
        if ($filter->dueDateFrom) {
            $query->where('due_date', '>=', $filter->dueDateFrom);
        }
        if ($filter->dueDateTo) {
            $query->where('due_date', '<=', $filter->dueDateTo);
        }

        // Search filter with locale-aware functionality
        if ($filter->hasSearch()) {
            $searchTerm = $filter->search;
            
            if ($filter->isLocaleSearchEnabled()) {
                // Search only in current locale with fallback to English
                $searchLocale = $filter->getSearchLocale();
                $fallbackLocale = config('app.fallback_locale', 'en');
                
                $query->where(function ($q) use ($searchTerm, $searchLocale, $fallbackLocale) {
                    // Search in current locale first
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$searchLocale}')) LIKE ?", ["%{$searchTerm}%"])
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$searchLocale}')) LIKE ?", ["%{$searchTerm}%"]);
                    
                    // If current locale is not the fallback, also search in fallback
                    if ($searchLocale !== $fallbackLocale) {
                        $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$fallbackLocale}')) LIKE ?", ["%{$searchTerm}%"])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$fallbackLocale}')) LIKE ?", ["%{$searchTerm}%"]);
                    }
                });
            } else {
                // Search across all available languages
                $availableLocales = array_keys(config('app.available_locales', ['en' => 'English']));
                
                $query->where(function ($q) use ($searchTerm, $availableLocales) {
                    foreach ($availableLocales as $locale) {
                        $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) LIKE ?", ["%{$searchTerm}%"])
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) LIKE ?", ["%{$searchTerm}%"]);
                    }
                });
            }
        }

        // Include completed filter
        if (!$filter->includeCompleted) {
            $query->where('status', '!=', Task::STATUS_COMPLETED);
        }

        // Include deleted filter
        if ($filter->includeDeleted) {
            $query->withTrashed();
        }

        // Sorting
        $query->orderBy($filter->sortBy, $filter->sortDirection);

        return $query;
    }

    /**
     * Get tasks with their completion percentage
     */
    public function getTasksWithCompletionPercentage(User $user): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->with('subtasks')
            ->get()
            ->map(function ($task) {
                $task->completion_percentage = $task->getCompletionPercentage();
                return $task;
            });
    }

    /**
     * Find tasks by translation content
     */
    public function findByTranslation(User $user, string $locale, string $field, string $value): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->whereJsonContains("{$field}->{$locale}", $value)
            ->get();
    }

    /**
     * Get tasks missing translations for a specific locale
     */
    public function getTasksMissingTranslation(User $user, string $locale): Collection
    {
        return $this->model->where('user_id', $user->id)
            ->where(function ($query) use ($locale) {
                $query->whereJsonDoesntContain("name->{$locale}", true)
                    ->orWhereNull("name->{$locale}");
            })
            ->get();
    }
}
