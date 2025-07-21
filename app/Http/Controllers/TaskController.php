<?php

namespace App\Http\Controllers;

use App\DTOs\Task\CreateTaskDTO;
use App\DTOs\Task\TaskFilterDTO;
use App\DTOs\Task\UpdateTaskDTO;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\TaskFilterRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskListResource;
use App\Http\Resources\TaskDetailResource;
use App\Models\Task;
use App\Services\Task\TaskService;
use App\Services\LocaleCacheService;
use App\Services\TranslationPerformanceMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends ApiController
{
    public function __construct(
        private TaskService $taskService,
        private LocaleCacheService $cacheService,
        private TranslationPerformanceMonitor $performanceMonitor
    ) {}

    /**
     * Display a listing of tasks with locale-aware responses and caching.
     */
    public function index(TaskFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $filterDTO = TaskFilterDTO::fromRequest($request);
        
        // Try to get from cache first
        $cacheKey = $filterDTO->toArray();
        $cachedTasks = $this->cacheService->getCachedTaskList($user->id, $locale, $cacheKey);
        
        if ($cachedTasks) {
            return response()->json($cachedTasks);
        }
        
        // Track performance of the database query
        $tasks = $this->performanceMonitor->trackQuery(
            'task_list',
            $locale,
            fn() => $this->taskService->getTasks($user, $filterDTO),
            [
                'user_id' => $user->id,
                'filters' => $cacheKey,
                'cached' => false,
            ]
        );
        
        // Use TaskListResource for optimized list view with minimal localized data
        $collection = TaskListResource::collection($tasks);
        
        // Add locale information to the response metadata
        $collection->additional([
            'meta' => [
                'locale' => $locale,
                'search_locale' => $filterDTO->isLocaleSearchEnabled() ? $filterDTO->getSearchLocale() : null,
                'locale_search_enabled' => $filterDTO->isLocaleSearchEnabled(),
                'fallback_locale' => config('app.fallback_locale', 'en'),
                'available_locales' => array_keys(config('app.available_locales', ['en' => 'English'])),
                'cached' => false,
                'cache_key' => md5(serialize($cacheKey)),
            ]
        ]);
        
        $response = $collection->response()->getData(true);
        
        // Cache the response
        $this->cacheService->cacheTaskList($user->id, $locale, $cacheKey, $response);
        
        return response()->json($response);
    }

    /**
     * Store a newly created task.
     */
    public function store(TaskRequest $request): JsonResponse
    {
        $dto = CreateTaskDTO::fromRequest($request);
        $task = $this->taskService->createTask($dto, $request->user());
        
        // Return detailed resource for newly created task
        return $this->success(new TaskDetailResource($task), 201);
    }

    /**
     * Display the specified task with proper fallback handling and caching.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locale = app()->getLocale();
        $includeTranslations = $request->boolean('include_translations', false);
        
        // Track performance of the database query
        $task = $this->performanceMonitor->trackQuery(
            'task_detail',
            $locale,
            fn() => $this->taskService->getTaskById($id, Auth::user()),
            [
                'task_id' => $id,
                'include_translations' => $includeTranslations,
                'user_id' => Auth::id(),
            ]
        );
        
        // Use TaskDetailResource when include_translations is requested or for editing
        if ($includeTranslations) {
            $resource = new TaskDetailResource($task);
            
            // Add additional locale metadata for editing context
            $resource->additional([
                'meta' => [
                    'locale' => $locale,
                    'fallback_locale' => config('app.fallback_locale', 'en'),
                    'available_locales' => config('app.available_locales', ['en' => 'English']),
                    'include_translations' => true,
                    'cached' => false,
                ]
            ]);
            
            return $this->success($resource);
        }
        
        // Default to TaskListResource for basic task view with fallback handling
        $resource = new TaskListResource($task);
        
        // Check if fallback was used and add warning if needed
        $fallbackUsed = !$task->hasTranslation('name', $locale);
        
        $additionalData = [
            'meta' => [
                'locale' => $locale,
                'fallback_locale' => config('app.fallback_locale', 'en'),
                'cached' => false,
            ]
        ];
        
        if ($fallbackUsed) {
            $additionalData['warnings'] = [
                'fallback_used' => [
                    'message' => "Content displayed in fallback language due to missing translation for '{$locale}'",
                    'fallback_locale' => config('app.fallback_locale', 'en'),
                    'missing_locale' => $locale,
                ]
            ];
        }
        
        $resource->additional($additionalData);
        
        return $this->success($resource);
    }

    /**
     * Update the specified task.
     */
    public function update(TaskRequest $request, Task $task): JsonResponse
    {
        $dto = UpdateTaskDTO::fromRequest($request);
        $updatedTask = $this->taskService->updateTask($task, $dto, $request->user());
        
        // Return detailed resource for updated task
        return $this->success(new TaskDetailResource($updatedTask));
    }

    /**
     * Soft delete the specified task.
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->taskService->deleteTask($task, Auth::user());
        return $this->success(null, 204);
    }

    /**
     * Search tasks with locale-aware functionality.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:255',
            'locale_search' => 'boolean',
            'search_locale' => 'nullable|string|in:' . implode(',', array_keys(config('app.available_locales', ['en']))),
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        // Create filter DTO with search parameters
        $filterData = $request->only([
            'status', 'priority', 'page', 'per_page', 
            'locale_search', 'search_locale'
        ]);
        $filterData['search'] = $request->input('query');
        $filterData['page'] = $request->input('page', 1);
        $filterData['per_page'] = $request->input('per_page', 15);
        $filterData['locale_search'] = $request->boolean('locale_search', true);

        $filterDTO = TaskFilterDTO::fromArray($filterData);
        $tasks = $this->taskService->getTasks($request->user(), $filterDTO);
        
        // Use TaskListResource for search results
        $collection = TaskListResource::collection($tasks);
        
        // Add search metadata
        $collection->additional([
            'meta' => [
                'search_query' => $request->input('query'),
                'search_locale' => $filterDTO->getSearchLocale(),
                'locale_search_enabled' => $filterDTO->isLocaleSearchEnabled(),
                'current_locale' => app()->getLocale(),
                'fallback_locale' => config('app.fallback_locale', 'en'),
                'available_locales' => array_keys(config('app.available_locales', ['en' => 'English'])),
                'total_results' => $tasks->total(),
            ]
        ]);
        
        return $collection->response();
    }
}