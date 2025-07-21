<?php

namespace App\Services\Task;

use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\DTOs\Task\TaskFilterDTO;
use App\DTOs\Task\TaskDTO;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidTaskHierarchyException;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\BaseService;
use App\Services\TaskCacheService;
use App\Services\TaskEventService;
use App\Services\TaskJobDispatcher;
use App\Services\LoggingService;
use App\Services\OptimizedTaskQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class TaskService extends BaseService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TaskCacheService $cacheService,
        private TaskEventService $eventService,
        private TaskJobDispatcher $jobDispatcher,
        private LoggingService $loggingService,
        private OptimizedTaskQueryService $optimizedQueryService
    ) {}

    /**
     * Create a new task for a user using DTO and repository.
     */
    public function createTask(CreateTaskDTO $dto, User $user): Task
    {
        return DB::transaction(function () use ($dto, $user) {
            // Business validation: parent task
            if ($dto->parentId) {
                $this->validateParentTask($dto->parentId, $user);
            }

            $task = $this->taskRepository->createFromDTO($dto, $user);
            $task->load(['subtasks', 'parent', 'user']);

            // Invalidate optimized query cache
            $this->optimizedQueryService->invalidateUserQueryCache($user->id);

            $taskDto = new TaskDTO($task);
            $this->eventService->broadcastTaskCreated($taskDto);
            $this->cacheService->clearTaskCache($task);

            LoggingService::logTaskOperation('task_created', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'parent_id' => $task->parent_id,
                'status' => $task->status,
                'priority' => $task->priority
            ]);

            return $task;
        });
    }

    /**
     * Update an existing task for a user using DTO and repository.
     */
    public function updateTask(Task $task, UpdateTaskDTO $dto, User $user): Task
    {
        return DB::transaction(function () use ($task, $dto, $user) {
            // Ownership check
            if ($task->user_id !== $user->id) {
                throw new TaskNotFoundException('Task not found or not owned by user.');
            }

            // Business validation: parent task
            if ($dto->parentId) {
                $this->validateParentTask($dto->parentId, $user, $task->id);
            }

            $originalData = $task->toArray();
            $task = $this->taskRepository->updateFromDTO($task, $dto);
            $task->load(['subtasks', 'parent', 'user']);

            $changes = $this->calculateChanges($originalData, $task->toArray());
            $this->cacheService->clearTaskCache($task);

            // Invalidate optimized query cache
            $this->optimizedQueryService->invalidateUserQueryCache($user->id);

            $taskDto = new TaskDTO($task);
            $this->eventService->broadcastTaskUpdated($taskDto, $changes);

            if (!empty($changes)) {
                $this->jobDispatcher->dispatchTaskUpdatedNotification($task, ['changes' => $changes]);
                if (isset($changes['status']) && $task->status === Task::STATUS_COMPLETED) {
                    $this->jobDispatcher->dispatchTaskCompletedNotification($task);
                }
            }
            LoggingService::logTaskOperation('task_updated', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'changes' => array_keys($changes)
            ]);
            return $task;
        });
    }

    /**
     * Delete (soft delete) a task for a user.
     */
    public function deleteTask(Task $task, User $user): void
    {
        DB::transaction(function () use ($task, $user) {
            if ($task->user_id !== $user->id) {
                throw new TaskNotFoundException('Task not found or not owned by user.');
            }
            $taskId = $task->id;
            $userId = $task->user_id;

            $task->delete();

            // Invalidate optimized query cache
            $this->optimizedQueryService->invalidateUserQueryCache($user->id);

            $this->eventService->broadcastTaskDeleted($taskId, $userId);

            $this->cacheService->clearTaskCache($task);
            $this->jobDispatcher->dispatchTaskDeletedNotification($task);
            LoggingService::logTaskOperation('task_deleted', [
                'task_id' => $taskId,
                'user_id' => $user->id
            ]);
        });
    }

    /**
     * Get tasks for a user with filtering and pagination using optimized queries.
     */
    public function getTasks(User $user, TaskFilterDTO $filterDTO)
    {
        $locale = app()->getLocale();

        // Use optimized query service for better performance
        return $this->optimizedQueryService->getOptimizedTaskList(
            $user,
            $filterDTO,
            $locale,
            $filterDTO->getPerPage() ?? 15
        );
    }

    /**
     * Get a single task by ID for a user with optimized query.
     */
    public function getTaskById(int $id, User $user): Task
    {
        $locale = app()->getLocale();

        // Try optimized query service first
        $task = $this->optimizedQueryService->getTaskWithTranslations($id, $user, $locale);

        if (!$task) {
            throw new TaskNotFoundException('Task not found.');
        }

        return $task;
    }

    /**
     * Get task statistics for a user with caching
     */
    public function getTaskStatistics(User $user): array
    {
        $locale = app()->getLocale();
        return $this->optimizedQueryService->getTaskStatistics($user, $locale);
    }

    /**
     * Business validation for parent task assignment.
     */
    private function validateParentTask(int $parentId, User $user, ?int $childId = null): void
    {
        $parentTask = $this->taskRepository->findByIdAndUser($parentId, $user);
        if (!$parentTask) {
            throw new TaskNotFoundException('Parent task not found.');
        }
        if ($parentTask->isSubtask()) {
            throw new InvalidTaskHierarchyException('Cannot create subtask of a subtask. Maximum nesting level is 2.');
        }
        if ($childId && $parentId === $childId) {
            throw new InvalidTaskHierarchyException('A task cannot be its own parent.');
        }
    }

    /**
     * Calculate changes between two arrays (for update tracking).
     */
    private function calculateChanges(array $original, array $updated): array
    {
        $changes = [];
        $fieldsToTrack = ['name', 'description', 'status', 'priority', 'due_date', 'parent_id'];
        foreach ($fieldsToTrack as $field) {
            if (isset($original[$field]) && isset($updated[$field])) {
                if ($original[$field] !== $updated[$field]) {
                    $changes[$field] = [
                        'from' => $original[$field],
                        'to' => $updated[$field]
                    ];
                }
            } elseif (!isset($original[$field]) && isset($updated[$field])) {
                $changes[$field] = [
                    'from' => null,
                    'to' => $updated[$field]
                ];
            } elseif (isset($original[$field]) && !isset($updated[$field])) {
                $changes[$field] = [
                    'from' => $original[$field],
                    'to' => null
                ];
            }
        }
        return $changes;
    }
}
