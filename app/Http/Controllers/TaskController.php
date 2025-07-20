<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Requests\TaskFilterRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskCacheService;
use App\Services\TaskEventService;
use App\Services\TaskJobDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\LoggingService;

class TaskController extends ApiController
{
    public function __construct(
        private TaskCacheService $cacheService,
        private TaskEventService $eventService,
        private TaskJobDispatcher $jobDispatcher
    ) {}

    /**
     * Display a listing of tasks with filtering, pagination, and caching
     *
     * @param TaskFilterRequest $request
     * @return JsonResponse
     */
    public function index(TaskFilterRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Extract filters from request
            $filters = $request->getFilters();
            $pagination = $request->getPagination();
            $sorting = $request->getSorting();
            
            $perPage = $pagination['per_page'];
            $page = $pagination['page'];
            
            // Try to get cached results first
            $cacheKey = "user:{$user->id}:tasks:" . md5(serialize($filters) . ":{$page}:{$perPage}");
            
            $tasks = $this->cacheService->getUserTasks($user->id, $filters);
            
            if ($tasks) {
                // Apply pagination to cached results
                $total = $tasks->count();
                $offset = ($page - 1) * $perPage;
                $paginatedTasks = $tasks->slice($offset, $perPage)->values();
                
                return response()->json([
                    'data' => TaskResource::collection($paginatedTasks),
                    'meta' => [
                        'current_page' => (int) $page,
                        'per_page' => (int) $perPage,
                        'total' => $total,
                        'last_page' => ceil($total / $perPage),
                        'from' => $offset + 1,
                        'to' => min($offset + $perPage, $total),
                    ],
                    'filters_applied' => $filters,
                    'sorting' => $sorting,
                    'cached' => true
                ]);
            }
            
            // If not cached, query database
            $query = Task::where('user_id', $user->id)
                ->with(['subtasks', 'parent']);
            
            // Apply filters
            $this->applyFilters($query, $filters);
            
            // Apply sorting
            $query->orderBy($sorting['sort_by'], $sorting['sort_direction']);
            
            // Get paginated results
            $paginatedResults = $query
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Cache the results
            $this->cacheService->getUserTasks($user->id, $filters);
            
            return response()->json([
                'data' => TaskResource::collection($paginatedResults->items()),
                'meta' => [
                    'current_page' => $paginatedResults->currentPage(),
                    'per_page' => $paginatedResults->perPage(),
                    'total' => $paginatedResults->total(),
                    'last_page' => $paginatedResults->lastPage(),
                    'from' => $paginatedResults->firstItem(),
                    'to' => $paginatedResults->lastItem(),
                ],
                'filters_applied' => $filters,
                'sorting' => $sorting,
                'cached' => false
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tasks', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'filters' => $filters ?? []
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve tasks',
                'message' => 'An error occurred while fetching your tasks. Please try again.'
            ], 500);
        }
    }

    /**
     * Store a newly created task with validation and queue job dispatching
     *
     * @param TaskRequest $request
     * @return JsonResponse
     */
    public function store(TaskRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get validated data from form request
            $validatedData = $request->validated();
            

            
            DB::beginTransaction();
            
            // Create the task
            $task = Task::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'status' => $validatedData['status'],
                'priority' => $validatedData['priority'],
                'due_date' => $validatedData['due_date'] ?? null,
                'parent_id' => $validatedData['parent_id'] ?? null,
                'user_id' => $user->id,
            ]);
            
            // Load relationships
            $task->load(['subtasks', 'parent', 'user']);
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($task);
            
            // Dispatch notification job
            $this->jobDispatcher->dispatchTaskCreatedNotification($task);
            
            // Broadcast event
            $this->eventService->broadcastTaskCreated($task);
            
            LoggingService::logTaskOperation('task_created', [
                'task_id' => $task->id,
                'parent_id' => $task->parent_id,
                'status' => $task->status,
                'priority' => $task->priority,
            ]);
            
            return response()->json([
                'data' => new TaskResource($task),
                'message' => 'Task created successfully'
            ], 201);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create task', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Failed to create task',
                'message' => 'An error occurred while creating the task. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified task with cached responses and eager loading
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Try to get from cache first
            $task = $this->cacheService->getTaskDetails($id);
            
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found',
                    'message' => 'The requested task could not be found.'
                ], 404);
            }
            
            // Check ownership
            if ($task->user_id !== $user->id) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You do not have permission to access this task.'
                ], 403);
            }
            
            // Cache the task details if not already cached
            $this->cacheService->cacheTaskDetails($task);
            
            return response()->json([
                'data' => new TaskResource($task)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve task', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve task',
                'message' => 'An error occurred while fetching the task. Please try again.'
            ], 500);
        }
    }

    /**
     * Update the specified task with cache invalidation and event broadcasting
     *
     * @param TaskRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(TaskRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the task
            $task = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found',
                    'message' => 'The requested task could not be found or you do not have permission to access it.'
                ], 404);
            }
            
            // Store original data for change tracking
            $originalData = $task->toArray();
            
            // Get validated data from form request
            $validatedData = $request->validated();
            

            
            DB::beginTransaction();
            
            // Update the task
            $task->update($validatedData);
            
            // Reload relationships
            $task->load(['subtasks', 'parent', 'user']);
            
            DB::commit();
            
            // Calculate changes
            $changes = $this->calculateChanges($originalData, $task->toArray());
            
            // Clear cache
            $this->cacheService->clearTaskCache($task);
            
            // Dispatch notification job if significant changes occurred
            if (!empty($changes)) {
                $this->jobDispatcher->dispatchTaskUpdatedNotification($task, ['changes' => $changes]);
                
                // Special handling for completion
                if (isset($changes['status']) && $task->status === Task::STATUS_COMPLETED) {
                    $this->jobDispatcher->dispatchTaskCompletedNotification($task);
                    $this->eventService->broadcastTaskCompleted($task);
                }
            }
            
            // Broadcast event
            $this->eventService->broadcastTaskUpdated($task, $changes);
            
            Log::info('Task updated successfully', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'changes' => array_keys($changes)
            ]);
            
            return response()->json([
                'data' => new TaskResource($task),
                'changes' => $changes,
                'message' => 'Task updated successfully'
            ]);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update task', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Failed to update task',
                'message' => 'An error occurred while updating the task. Please try again.'
            ], 500);
        }
    }

    /**
     * Soft delete the specified task with cache clearing
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the task
            $task = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found',
                    'message' => 'The requested task could not be found or you do not have permission to access it.'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Soft delete the task
            $task->delete();
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($task);
            
            // Dispatch notification job
            $this->jobDispatcher->dispatchTaskDeletedNotification($task);
            
            // Broadcast event
            $this->eventService->broadcastTaskDeleted($task);
            
            Log::info('Task deleted successfully', [
                'task_id' => $task->id,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'message' => 'Task deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete task', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to delete task',
                'message' => 'An error occurred while deleting the task. Please try again.'
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted task
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the soft-deleted task
            $task = Task::onlyTrashed()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found',
                    'message' => 'The requested deleted task could not be found or you do not have permission to access it.'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Restore the task
            $task->restore();
            
            // Reload relationships
            $task->load(['subtasks', 'parent', 'user']);
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($task);
            
            // Broadcast event
            $this->eventService->broadcastTaskRestored($task);
            
            Log::info('Task restored successfully', [
                'task_id' => $task->id,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'data' => new TaskResource($task),
                'message' => 'Task restored successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to restore task', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to restore task',
                'message' => 'An error occurred while restoring the task. Please try again.'
            ], 500);
        }
    }



    /**
     * Apply filters to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null' || $filters['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }
        
        if (isset($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }
        
        if (isset($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                // Search in English name and description
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.en')) LIKE ?", ["%{$search}%"])
                  // Search in French name and description
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.fr')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.fr')) LIKE ?", ["%{$search}%"])
                  // Search in German name and description
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.de')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.de')) LIKE ?", ["%{$search}%"]);
            });
        }
        
        if (isset($filters['hierarchy_level'])) {
            switch ($filters['hierarchy_level']) {
                case 'root':
                    $query->whereNull('parent_id');
                    break;
                case 'subtasks':
                    $query->whereNotNull('parent_id');
                    break;
                // 'all' doesn't add any filter
            }
        }
        
        if (isset($filters['include_completed']) && !$filters['include_completed']) {
            $query->where('status', '!=', 'completed');
        }
        
        if (isset($filters['include_deleted']) && $filters['include_deleted']) {
            $query->withTrashed();
        }
    }

    /**
     * Check if setting a parent would create a circular reference
     *
     * @param int $taskId
     * @param int $parentId
     * @return bool
     */
    private function wouldCreateCircularReference(int $taskId, int $parentId): bool
    {
        // Get all subtasks of the current task
        $subtaskIds = $this->getAllSubtaskIds($taskId);
        
        // If the proposed parent is in the subtask chain, it would create a circular reference
        return in_array($parentId, $subtaskIds);
    }

    /**
     * Get all subtask IDs recursively
     *
     * @param int $taskId
     * @return array
     */
    private function getAllSubtaskIds(int $taskId): array
    {
        $subtaskIds = [];
        
        $subtasks = Task::where('parent_id', $taskId)->pluck('id')->toArray();
        
        foreach ($subtasks as $subtaskId) {
            $subtaskIds[] = $subtaskId;
            $subtaskIds = array_merge($subtaskIds, $this->getAllSubtaskIds($subtaskId));
        }
        
        return $subtaskIds;
    }

    /**
     * Calculate changes between original and updated data
     *
     * @param array $original
     * @param array $updated
     * @return array
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

    /**
     * Get subtasks for a specific task
     *
     * @param int $id
     * @return JsonResponse
     */
    public function subtasks(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the parent task
            $parentTask = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$parentTask) {
                return $this->notFoundResponse('The requested task could not be found or you do not have permission to access it.');
            }
            
            // Get subtasks with their relationships
            $subtasks = Task::where('parent_id', $id)
                ->where('user_id', $user->id)
                ->with(['subtasks', 'parent'])
                ->orderBy('created_at', 'asc')
                ->get();
            
            return response()->json([
                'data' => TaskResource::collection($subtasks),
                'parent' => new TaskResource($parentTask),
                'meta' => [
                    'total_subtasks' => $subtasks->count(),
                    'completed_subtasks' => $subtasks->where('status', 'completed')->count(),
                    'completion_percentage' => $parentTask->getCompletionPercentage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve subtasks', [
                'parent_task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('An error occurred while fetching subtasks. Please try again.');
        }
    }

    /**
     * Create a new subtask for a specific parent task
     *
     * @param TaskRequest $request
     * @param int $parentId
     * @return JsonResponse
     */
    public function createSubtask(TaskRequest $request, int $parentId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the parent task
            $parentTask = Task::where('id', $parentId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$parentTask) {
                return $this->notFoundResponse('The specified parent task could not be found or you do not have permission to access it.');
            }
            
            // Check if parent is already a subtask (prevent deep nesting)
            if ($parentTask->isSubtask()) {
                return $this->errorResponse('Cannot create subtask of a subtask. Maximum nesting level is 2.', 422);
            }
            
            // Get validated data and force parent_id
            $validatedData = $request->validated();
            $validatedData['parent_id'] = $parentId;
            
            DB::beginTransaction();
            
            // Create the subtask
            $subtask = Task::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'status' => $validatedData['status'],
                'priority' => $validatedData['priority'],
                'due_date' => $validatedData['due_date'] ?? null,
                'parent_id' => $parentId,
                'user_id' => $user->id,
            ]);
            
            // Load relationships
            $subtask->load(['subtasks', 'parent', 'user']);
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($subtask);
            $this->cacheService->clearTaskCache($parentTask);
            
            // Dispatch notification job
            $this->jobDispatcher->dispatchTaskCreatedNotification($subtask, ['parent_task' => $parentTask->toArray()]);
            
            // Broadcast events
            $this->eventService->broadcastTaskCreated($subtask);
            $this->eventService->broadcastSubtaskParentUpdated($parentTask);
            
            Log::info('Subtask created successfully', [
                'subtask_id' => $subtask->id,
                'parent_task_id' => $parentId,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'data' => new TaskResource($subtask),
                'parent' => new TaskResource($parentTask),
                'message' => 'Subtask created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create subtask', [
                'parent_task_id' => $parentId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return $this->serverErrorResponse('An error occurred while creating the subtask. Please try again.');
        }
    }

    /**
     * Reorder subtasks for a parent task
     *
     * @param Request $request
     * @param int $parentId
     * @return JsonResponse
     */
    public function reorderSubtasks(Request $request, int $parentId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Validate request
            $validatedData = $request->validate([
                'subtask_ids' => 'required|array',
                'subtask_ids.*' => 'required|integer|exists:tasks,id',
            ]);
            
            // Find the parent task
            $parentTask = Task::where('id', $parentId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$parentTask) {
                return $this->notFoundResponse('The specified parent task could not be found or you do not have permission to access it.');
            }
            
            // Verify all subtasks belong to the parent and user
            $subtaskIds = $validatedData['subtask_ids'];
            $existingSubtasks = Task::where('parent_id', $parentId)
                ->where('user_id', $user->id)
                ->whereIn('id', $subtaskIds)
                ->pluck('id')
                ->toArray();
                
            if (count($existingSubtasks) !== count($subtaskIds)) {
                return $this->errorResponse('Some subtasks do not belong to the specified parent task or you do not have permission to access them.', 422);
            }
            
            DB::beginTransaction();
            
            // Update the order by updating created_at timestamps
            // This is a simple approach - in production you might want a dedicated order column
            $baseTime = now();
            foreach ($subtaskIds as $index => $subtaskId) {
                Task::where('id', $subtaskId)->update([
                    'updated_at' => $baseTime->copy()->addSeconds($index)
                ]);
            }
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($parentTask);
            
            // Get reordered subtasks
            $reorderedSubtasks = Task::where('parent_id', $parentId)
                ->where('user_id', $user->id)
                ->with(['subtasks', 'parent'])
                ->orderBy('updated_at', 'asc')
                ->get();
            
            // Broadcast event
            $this->eventService->broadcastSubtaskParentUpdated($parentTask);
            
            Log::info('Subtasks reordered successfully', [
                'parent_task_id' => $parentId,
                'user_id' => $user->id,
                'new_order' => $subtaskIds
            ]);
            
            return response()->json([
                'data' => TaskResource::collection($reorderedSubtasks),
                'parent' => new TaskResource($parentTask),
                'message' => 'Subtasks reordered successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to reorder subtasks', [
                'parent_task_id' => $parentId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return $this->serverErrorResponse('An error occurred while reordering subtasks. Please try again.');
        }
    }

    /**
     * Move a subtask to a different parent or make it a root task
     *
     * @param Request $request
     * @param int $subtaskId
     * @return JsonResponse
     */
    public function moveSubtask(Request $request, int $subtaskId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Validate request
            $validatedData = $request->validate([
                'new_parent_id' => 'nullable|integer|exists:tasks,id',
            ]);
            
            // Find the subtask
            $subtask = Task::where('id', $subtaskId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$subtask) {
                return $this->notFoundResponse('The specified subtask could not be found or you do not have permission to access it.');
            }
            
            $oldParentId = $subtask->parent_id;
            $newParentId = $validatedData['new_parent_id'] ?? null;
            
            // If new parent is specified, validate it
            if ($newParentId) {
                $newParentTask = Task::where('id', $newParentId)
                    ->where('user_id', $user->id)
                    ->first();
                    
                if (!$newParentTask) {
                    return $this->errorResponse('The specified new parent task could not be found or you do not have permission to access it.', 422);
                }
                
                // Prevent self-reference
                if ($newParentId == $subtaskId) {
                    return $this->errorResponse('A task cannot be its own parent.', 422);
                }
                
                // Check if new parent is already a subtask (prevent deep nesting)
                if ($newParentTask->isSubtask()) {
                    return $this->errorResponse('Cannot create subtask of a subtask. Maximum nesting level is 2.', 422);
                }
                
                // Check for circular references
                if ($this->wouldCreateCircularReference($subtaskId, $newParentId)) {
                    return $this->errorResponse('This would create a circular reference in the task hierarchy.', 422);
                }
            }
            
            DB::beginTransaction();
            
            // Update the subtask's parent
            $subtask->update(['parent_id' => $newParentId]);
            
            // Reload relationships
            $subtask->load(['subtasks', 'parent', 'user']);
            
            DB::commit();
            
            // Clear cache for all affected tasks
            $this->cacheService->clearTaskCache($subtask);
            
            if ($oldParentId) {
                $oldParent = Task::find($oldParentId);
                if ($oldParent) {
                    $this->cacheService->clearTaskCache($oldParent);
                    $this->eventService->broadcastSubtaskParentUpdated($oldParent);
                }
            }
            
            if ($newParentId) {
                $newParent = Task::find($newParentId);
                if ($newParent) {
                    $this->cacheService->clearTaskCache($newParent);
                    $this->eventService->broadcastSubtaskParentUpdated($newParent);
                }
            }
            
            // Broadcast events
            $this->eventService->broadcastTaskUpdated($subtask, ['parent_id' => ['from' => $oldParentId, 'to' => $newParentId]]);
            
            Log::info('Subtask moved successfully', [
                'subtask_id' => $subtaskId,
                'old_parent_id' => $oldParentId,
                'new_parent_id' => $newParentId,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'data' => new TaskResource($subtask),
                'message' => $newParentId ? 'Subtask moved to new parent successfully' : 'Subtask converted to root task successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to move subtask', [
                'subtask_id' => $subtaskId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return $this->serverErrorResponse('An error occurred while moving the subtask. Please try again.');
        }
    }

    /**
     * Bulk operations on subtasks
     *
     * @param Request $request
     * @param int $parentId
     * @return JsonResponse
     */
    public function bulkSubtaskOperations(Request $request, int $parentId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Validate request
            $validatedData = $request->validate([
                'operation' => 'required|in:complete,delete,restore,update_status,update_priority',
                'subtask_ids' => 'required|array',
                'subtask_ids.*' => 'required|integer|exists:tasks,id',
                'status' => 'required_if:operation,update_status|in:' . implode(',', Task::getStatuses()),
                'priority' => 'required_if:operation,update_priority|in:' . implode(',', Task::getPriorities()),
            ]);
            
            // Find the parent task
            $parentTask = Task::where('id', $parentId)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$parentTask) {
                return $this->notFoundResponse('The specified parent task could not be found or you do not have permission to access it.');
            }
            
            // Get subtasks
            $subtasks = Task::where('parent_id', $parentId)
                ->where('user_id', $user->id)
                ->whereIn('id', $validatedData['subtask_ids'])
                ->get();
                
            if ($subtasks->count() !== count($validatedData['subtask_ids'])) {
                return $this->errorResponse('Some subtasks do not belong to the specified parent task or you do not have permission to access them.', 422);
            }
            
            DB::beginTransaction();
            
            $operation = $validatedData['operation'];
            $updatedTasks = [];
            
            foreach ($subtasks as $subtask) {
                switch ($operation) {
                    case 'complete':
                        $subtask->update(['status' => Task::STATUS_COMPLETED]);
                        $this->jobDispatcher->dispatchTaskCompletedNotification($subtask);
                        $this->eventService->broadcastTaskCompleted($subtask);
                        break;
                        
                    case 'delete':
                        $subtask->delete();
                        $this->jobDispatcher->dispatchTaskDeletedNotification($subtask);
                        $this->eventService->broadcastTaskDeleted($subtask);
                        break;
                        
                    case 'restore':
                        if ($subtask->trashed()) {
                            $subtask->restore();
                            $this->eventService->broadcastTaskRestored($subtask);
                        }
                        break;
                        
                    case 'update_status':
                        $subtask->update(['status' => $validatedData['status']]);
                        $this->jobDispatcher->dispatchTaskUpdatedNotification($subtask);
                        $this->eventService->broadcastTaskUpdated($subtask, ['status' => ['to' => $validatedData['status']]]);
                        break;
                        
                    case 'update_priority':
                        $subtask->update(['priority' => $validatedData['priority']]);
                        $this->jobDispatcher->dispatchTaskUpdatedNotification($subtask);
                        $this->eventService->broadcastTaskUpdated($subtask, ['priority' => ['to' => $validatedData['priority']]]);
                        break;
                }
                
                $updatedTasks[] = $subtask->fresh(['subtasks', 'parent', 'user']);
            }
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($parentTask);
            foreach ($updatedTasks as $task) {
                $this->cacheService->clearTaskCache($task);
            }
            
            // Broadcast parent update
            $this->eventService->broadcastSubtaskParentUpdated($parentTask);
            
            Log::info('Bulk subtask operation completed', [
                'operation' => $operation,
                'parent_task_id' => $parentId,
                'subtask_count' => count($updatedTasks),
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'data' => TaskResource::collection($updatedTasks),
                'parent' => new TaskResource($parentTask->fresh(['subtasks', 'parent'])),
                'message' => "Bulk {$operation} operation completed successfully on " . count($updatedTasks) . " subtasks"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to perform bulk subtask operation', [
                'parent_task_id' => $parentId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return $this->serverErrorResponse('An error occurred while performing the bulk operation. Please try again.');
        }
    }

    /**

     * Get translation information for a specific task
     *
     * @param int $id
     * @return JsonResponse
     */
    public function translations(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the task
            $task = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found',
                    'message' => 'The requested task could not be found or you do not have permission to access it.'
                ], 404);
            }
            
            return response()->json([
                'task_id' => $task->id,
                'translations' => [
                    'name' => $task->getFieldTranslations('name'),
                    'description' => $task->getFieldTranslations('description'),
                ],
                'completeness' => $task->getTranslationCompleteness(),
                'available_locales' => array_keys(config('app.available_locales', ['en' => 'English'])),
                'current_locale' => app()->getLocale(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve task translations', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to retrieve translations',
                'message' => 'An error occurred while fetching task translations. Please try again.'
            ], 500);
        }
    }

    /**
     * Update translations for a specific task
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateTranslations(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the task
            $task = Task::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$task) {
                return response()->json([
                    'error' => 'Task not found',
                    'message' => 'The requested task could not be found or you do not have permission to access it.'
                ], 404);
            }
            
            // Validate request
            $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
            $validatedData = $request->validate([
                'translations' => 'required|array',
                'translations.name' => 'sometimes|array',
                'translations.description' => 'sometimes|array',
            ]);
            
            // Validate each locale
            foreach ($validatedData['translations'] as $field => $translations) {
                if (!in_array($field, ['name', 'description'])) {
                    return response()->json([
                        'error' => 'Invalid field',
                        'message' => "Field '{$field}' is not translatable."
                    ], 422);
                }
                
                foreach ($translations as $locale => $value) {
                    if (!in_array($locale, $supportedLocales)) {
                        return response()->json([
                            'error' => 'Invalid locale',
                            'message' => "Locale '{$locale}' is not supported."
                        ], 422);
                    }
                    
                    // Validate field length
                    $maxLength = $field === 'name' ? 255 : 1000;
                    if (strlen($value) > $maxLength) {
                        return response()->json([
                            'error' => 'Validation failed',
                            'message' => "The {$field} in {$locale} may not be greater than {$maxLength} characters."
                        ], 422);
                    }
                }
            }
            
            DB::beginTransaction();
            
            // Update translations
            foreach ($validatedData['translations'] as $field => $translations) {
                foreach ($translations as $locale => $value) {
                    $task->setTranslation($field, $locale, $value);
                }
            }
            
            // Ensure English name is present (required)
            if (!$task->hasTranslation('name', 'en')) {
                return response()->json([
                    'error' => 'Validation failed',
                    'message' => 'English name translation is required.'
                ], 422);
            }
            
            $task->save();
            
            // Reload relationships
            $task->load(['subtasks', 'parent', 'user']);
            
            DB::commit();
            
            // Clear cache
            $this->cacheService->clearTaskCache($task);
            
            // Broadcast event
            $this->eventService->broadcastTaskUpdated($task, ['translations_updated' => true]);
            
            Log::info('Task translations updated successfully', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'updated_fields' => array_keys($validatedData['translations'])
            ]);
            
            return response()->json([
                'data' => new TaskResource($task),
                'translations' => [
                    'name' => $task->getFieldTranslations('name'),
                    'description' => $task->getFieldTranslations('description'),
                ],
                'completeness' => $task->getTranslationCompleteness(),
                'message' => 'Task translations updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update task translations', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Failed to update translations',
                'message' => 'An error occurred while updating task translations. Please try again.'
            ], 500);
        }
    }

    /**
     * Get translation completeness report for user's tasks
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function translationReport(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get all user's tasks
            $tasks = Task::where('user_id', $user->id)
                ->select(['id', 'name', 'description', 'status'])
                ->get();
            
            $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
            $report = [
                'total_tasks' => $tasks->count(),
                'locales' => [],
                'overall_completeness' => [],
            ];
            
            // Calculate completeness for each locale
            foreach ($supportedLocales as $locale) {
                $completeNames = 0;
                $completeDescriptions = 0;
                
                foreach ($tasks as $task) {
                    if ($task->hasTranslation('name', $locale)) {
                        $completeNames++;
                    }
                    if ($task->hasTranslation('description', $locale)) {
                        $completeDescriptions++;
                    }
                }
                
                $report['locales'][$locale] = [
                    'name' => config("app.available_locales.{$locale}", $locale),
                    'completeness' => [
                        'names' => [
                            'complete' => $completeNames,
                            'total' => $tasks->count(),
                            'percentage' => $tasks->count() > 0 ? round(($completeNames / $tasks->count()) * 100, 2) : 0,
                        ],
                        'descriptions' => [
                            'complete' => $completeDescriptions,
                            'total' => $tasks->count(),
                            'percentage' => $tasks->count() > 0 ? round(($completeDescriptions / $tasks->count()) * 100, 2) : 0,
                        ],
                    ],
                ];
            }
            
            // Calculate overall completeness
            $totalPossibleTranslations = $tasks->count() * count($supportedLocales);
            $totalCompleteNames = 0;
            $totalCompleteDescriptions = 0;
            
            foreach ($tasks as $task) {
                foreach ($supportedLocales as $locale) {
                    if ($task->hasTranslation('name', $locale)) {
                        $totalCompleteNames++;
                    }
                    if ($task->hasTranslation('description', $locale)) {
                        $totalCompleteDescriptions++;
                    }
                }
            }
            
            $report['overall_completeness'] = [
                'names' => [
                    'complete' => $totalCompleteNames,
                    'total' => $totalPossibleTranslations,
                    'percentage' => $totalPossibleTranslations > 0 ? round(($totalCompleteNames / $totalPossibleTranslations) * 100, 2) : 0,
                ],
                'descriptions' => [
                    'complete' => $totalCompleteDescriptions,
                    'total' => $totalPossibleTranslations,
                    'percentage' => $totalPossibleTranslations > 0 ? round(($totalCompleteDescriptions / $totalPossibleTranslations) * 100, 2) : 0,
                ],
            ];
            
            return response()->json([
                'report' => $report,
                'supported_locales' => config('app.available_locales', ['en' => 'English']),
                'current_locale' => app()->getLocale(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate translation report', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to generate report',
                'message' => 'An error occurred while generating the translation report. Please try again.'
            ], 500);
        }
    }
}