<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use App\Models\User;
use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\DTOs\Task\TaskFilterDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Task repository interface for data access operations
 */
interface TaskRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Create a new task from DTO
     */
    public function createFromDTO(CreateTaskDTO $dto, User $user): Task;

    /**
     * Update a task from DTO
     */
    public function updateFromDTO(Task $task, UpdateTaskDTO $dto): Task;

    /**
     * Find a task by ID and user
     */
    public function findByIdAndUser(int $id, User $user): ?Task;

    /**
     * Get tasks for a user with optional filtering
     */
    public function getTasksForUser(User $user, ?TaskFilterDTO $filter = null): Collection;

    /**
     * Get paginated tasks for a user with optional filtering
     */
    public function getPaginatedTasksForUser(User $user, ?TaskFilterDTO $filter = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get subtasks for a parent task
     */
    public function getSubtasks(Task $parentTask): Collection;

    /**
     * Get tasks by status for a user
     */
    public function getTasksByStatus(User $user, string $status): Collection;

    /**
     * Get overdue tasks for a user
     */
    public function getOverdueTasks(User $user): Collection;

    /**
     * Get completed tasks for a user
     */
    public function getCompletedTasks(User $user): Collection;

    /**
     * Count tasks by status for a user
     */
    public function countTasksByStatus(User $user, string $status): int;
}