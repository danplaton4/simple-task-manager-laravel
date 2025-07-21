<?php

namespace App\DTOs\Task;

use App\DTOs\BaseDTO;
use App\Http\Requests\TaskFilterRequest;
use App\Models\Task;
use Carbon\Carbon;

class TaskFilterDTO extends BaseDTO
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly ?int $parentId = null,
        public readonly ?Carbon $dueDateFrom = null,
        public readonly ?Carbon $dueDateTo = null,
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly bool $includeSubtasks = true,
        public readonly bool $includeCompleted = true,
        public readonly bool $includeDeleted = false,
        public readonly ?string $datePreset = null,
        public readonly string $hierarchyLevel = 'all',
        public readonly bool $localeSearch = true, // Whether to search only in current locale
        public readonly ?string $searchLocale = null // Specific locale to search in
    ) {}

    /**
     * Create DTO from TaskFilterRequest.
     */
    public static function fromRequest(TaskFilterRequest $request): self
    {
        $filters = $request->getFilters();
        $pagination = $request->getPagination();
        $sorting = $request->getSorting();
        
        return new self(
            status: $filters['status'] ?? null,
            priority: $filters['priority'] ?? null,
            parentId: $filters['parent_id'] ?? null,
            dueDateFrom: isset($filters['due_date_from']) ? Carbon::parse($filters['due_date_from']) : null,
            dueDateTo: isset($filters['due_date_to']) ? Carbon::parse($filters['due_date_to']) : null,
            search: $filters['search'] ?? null,
            page: $pagination['page'],
            perPage: $pagination['per_page'],
            sortBy: $sorting['sort_by'],
            sortDirection: $sorting['sort_direction'],
            includeSubtasks: $filters['include_subtasks'] ?? true,
            includeCompleted: $filters['include_completed'] ?? true,
            includeDeleted: $filters['include_deleted'] ?? false,
            datePreset: $request->input('date_preset'),
            hierarchyLevel: $filters['hierarchy_level'] ?? 'all',
            localeSearch: $request->boolean('locale_search', true),
            searchLocale: $request->input('search_locale')
        );
    }

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? null,
            priority: $data['priority'] ?? null,
            parentId: $data['parent_id'] ?? null,
            dueDateFrom: isset($data['due_date_from']) ? Carbon::parse($data['due_date_from']) : null,
            dueDateTo: isset($data['due_date_to']) ? Carbon::parse($data['due_date_to']) : null,
            search: $data['search'] ?? null,
            page: $data['page'] ?? 1,
            perPage: $data['per_page'] ?? 15,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc',
            includeSubtasks: $data['include_subtasks'] ?? true,
            includeCompleted: $data['include_completed'] ?? true,
            includeDeleted: $data['include_deleted'] ?? false,
            datePreset: $data['date_preset'] ?? null,
            hierarchyLevel: $data['hierarchy_level'] ?? 'all',
            localeSearch: $data['locale_search'] ?? true,
            searchLocale: $data['search_locale'] ?? null
        );
    }

    /**
     * Get filters as array for caching keys.
     */
    public function getFiltersArray(): array
    {
        return [
            'status' => $this->status,
            'priority' => $this->priority,
            'parent_id' => $this->parentId,
            'due_date_from' => $this->dueDateFrom?->toDateString(),
            'due_date_to' => $this->dueDateTo?->toDateString(),
            'search' => $this->search,
            'include_subtasks' => $this->includeSubtasks,
            'include_completed' => $this->includeCompleted,
            'include_deleted' => $this->includeDeleted,
            'hierarchy_level' => $this->hierarchyLevel,
            'locale_search' => $this->localeSearch,
            'search_locale' => $this->searchLocale,
        ];
    }

    /**
     * Get pagination parameters.
     */
    public function getPaginationArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * Get sorting parameters.
     */
    public function getSortingArray(): array
    {
        return [
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }

    /**
     * Generate cache key for these filters.
     */
    public function getCacheKey(int $userId): string
    {
        $filters = $this->getFiltersArray();
        $pagination = $this->getPaginationArray();
        $sorting = $this->getSortingArray();
        
        $keyData = array_merge($filters, $pagination, $sorting);
        
        return "user:{$userId}:tasks:" . md5(serialize($keyData));
    }

    /**
     * Validate the DTO data.
     */
    public function validate(): array
    {
        $errors = [];

        // Validate status
        if ($this->status && !in_array($this->status, Task::getStatuses())) {
            $errors['status'] = 'Invalid status. Valid options are: ' . implode(', ', Task::getStatuses());
        }

        // Validate priority
        if ($this->priority && !in_array($this->priority, Task::getPriorities())) {
            $errors['priority'] = 'Invalid priority. Valid options are: ' . implode(', ', Task::getPriorities());
        }

        // Validate parent ID
        if ($this->parentId && $this->parentId <= 0) {
            $errors['parent_id'] = 'Parent ID must be a positive integer';
        }

        // Validate date range
        if ($this->dueDateFrom && $this->dueDateTo && $this->dueDateFrom->gt($this->dueDateTo)) {
            $errors['due_date_range'] = 'Due date from must be before due date to';
        }

        // Validate pagination
        if ($this->page < 1) {
            $errors['page'] = 'Page must be at least 1';
        }

        if ($this->perPage < 1 || $this->perPage > 100) {
            $errors['per_page'] = 'Per page must be between 1 and 100';
        }

        // Validate sorting
        $validSortFields = ['created_at', 'updated_at', 'due_date', 'priority', 'status', 'name'];
        if (!in_array($this->sortBy, $validSortFields)) {
            $errors['sort_by'] = 'Invalid sort field. Valid options are: ' . implode(', ', $validSortFields);
        }

        if (!in_array($this->sortDirection, ['asc', 'desc'])) {
            $errors['sort_direction'] = 'Sort direction must be asc or desc';
        }

        // Validate hierarchy level
        $validHierarchyLevels = ['root', 'subtasks', 'all'];
        if (!in_array($this->hierarchyLevel, $validHierarchyLevels)) {
            $errors['hierarchy_level'] = 'Invalid hierarchy level. Valid options are: ' . implode(', ', $validHierarchyLevels);
        }

        return $errors;
    }

    /**
     * Check if the DTO is valid.
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Check if any filters are applied.
     */
    public function hasFilters(): bool
    {
        return $this->status !== null ||
               $this->priority !== null ||
               $this->parentId !== null ||
               $this->dueDateFrom !== null ||
               $this->dueDateTo !== null ||
               $this->search !== null ||
               !$this->includeCompleted ||
               $this->includeDeleted ||
               $this->hierarchyLevel !== 'all';
    }

    /**
     * Check if searching for root tasks only.
     */
    public function isRootTasksOnly(): bool
    {
        return $this->hierarchyLevel === 'root';
    }

    /**
     * Check if searching for subtasks only.
     */
    public function isSubtasksOnly(): bool
    {
        return $this->hierarchyLevel === 'subtasks';
    }

    /**
     * Check if date range filter is applied.
     */
    public function hasDateRange(): bool
    {
        return $this->dueDateFrom !== null || $this->dueDateTo !== null;
    }

    /**
     * Check if search filter is applied.
     */
    public function hasSearch(): bool
    {
        return $this->search !== null && trim($this->search) !== '';
    }

    /**
     * Get offset for pagination.
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Check if locale-aware search is enabled.
     */
    public function isLocaleSearchEnabled(): bool
    {
        return $this->localeSearch && $this->hasSearch();
    }

    /**
     * Get the locale to search in (specific locale or current app locale).
     */
    public function getSearchLocale(): string
    {
        return $this->searchLocale ?? app()->getLocale();
    }

    /**
     * Check if searching in all locales.
     */
    public function isSearchingAllLocales(): bool
    {
        return !$this->localeSearch && $this->hasSearch();
    }
}