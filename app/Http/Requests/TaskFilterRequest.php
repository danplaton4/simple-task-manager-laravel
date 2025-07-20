<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Status filter
            'status' => [
                'sometimes',
                'string',
                Rule::in(Task::getStatuses())
            ],
            
            // Priority filter
            'priority' => [
                'sometimes',
                'string',
                Rule::in(Task::getPriorities())
            ],
            
            // Parent task filter
            'parent_id' => [
                'sometimes',
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === 'null' || $value === null) {
                        return; // Valid for root tasks filter
                    }
                    
                    if (!is_numeric($value)) {
                        $fail('The parent task ID must be a number or "null".');
                        return;
                    }
                    
                    // Check if parent task exists and belongs to user
                    $parentExists = Task::where('id', $value)
                        ->where('user_id', Auth::id())
                        ->exists();
                        
                    if (!$parentExists) {
                        $fail('The selected parent task does not exist or you do not have permission to access it.');
                    }
                }
            ],
            
            // Due date range filters
            'due_date_from' => 'sometimes|date',
            'due_date_to' => 'sometimes|date|after_or_equal:due_date_from',
            
            // Search query
            'search' => 'sometimes|string|max:255',
            
            // Pagination
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            
            // Sorting
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in(['created_at', 'updated_at', 'due_date', 'priority', 'status', 'name'])
            ],
            'sort_direction' => [
                'sometimes',
                'string',
                Rule::in(['asc', 'desc'])
            ],
            
            // Include options
            'include_subtasks' => 'sometimes|boolean',
            'include_completed' => 'sometimes|boolean',
            'include_deleted' => 'sometimes|boolean',
            
            // Date range presets
            'date_preset' => [
                'sometimes',
                'string',
                Rule::in(['today', 'tomorrow', 'this_week', 'next_week', 'this_month', 'next_month', 'overdue'])
            ],
            
            // Task hierarchy filters
            'hierarchy_level' => [
                'sometimes',
                'string',
                Rule::in(['root', 'subtasks', 'all'])
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The selected status is invalid. Valid options are: ' . implode(', ', Task::getStatuses()),
            'priority.in' => 'The selected priority is invalid. Valid options are: ' . implode(', ', Task::getPriorities()),
            'due_date_from.date' => 'The due date from must be a valid date.',
            'due_date_to.date' => 'The due date to must be a valid date.',
            'due_date_to.after_or_equal' => 'The due date to must be after or equal to the due date from.',
            'search.max' => 'The search query may not be greater than 255 characters.',
            'page.integer' => 'The page must be an integer.',
            'page.min' => 'The page must be at least 1.',
            'per_page.integer' => 'The per page value must be an integer.',
            'per_page.min' => 'The per page value must be at least 1.',
            'per_page.max' => 'The per page value may not be greater than 100.',
            'sort_by.in' => 'The sort by field is invalid. Valid options are: created_at, updated_at, due_date, priority, status, name.',
            'sort_direction.in' => 'The sort direction must be either asc or desc.',
            'include_subtasks.boolean' => 'The include subtasks field must be true or false.',
            'include_completed.boolean' => 'The include completed field must be true or false.',
            'include_deleted.boolean' => 'The include deleted field must be true or false.',
            'date_preset.in' => 'The selected date preset is invalid.',
            'hierarchy_level.in' => 'The hierarchy level must be one of: root, subtasks, all.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'due_date_from' => 'due date from',
            'due_date_to' => 'due date to',
            'per_page' => 'items per page',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction',
            'include_subtasks' => 'include subtasks',
            'include_completed' => 'include completed tasks',
            'include_deleted' => 'include deleted tasks',
            'date_preset' => 'date preset',
            'hierarchy_level' => 'hierarchy level',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate date preset conflicts
            if ($this->has('date_preset') && ($this->has('due_date_from') || $this->has('due_date_to'))) {
                $validator->errors()->add(
                    'date_preset',
                    'Cannot use date preset together with custom date range filters.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert string booleans to actual booleans
        $booleanFields = ['include_subtasks', 'include_completed', 'include_deleted'];
        
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    $this->merge([
                        $field => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ]);
                }
            }
        }
        
        // Set default values
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 15),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
            'hierarchy_level' => $this->input('hierarchy_level', 'all'),
        ]);
        
        // Handle date presets
        if ($this->has('date_preset')) {
            $dateRange = $this->getDateRangeFromPreset($this->input('date_preset'));
            if ($dateRange) {
                $this->merge($dateRange);
            }
        }
    }

    /**
     * Get date range from preset
     *
     * @param string $preset
     * @return array|null
     */
    private function getDateRangeFromPreset(string $preset): ?array
    {
        $now = now();
        
        return match ($preset) {
            'today' => [
                'due_date_from' => $now->startOfDay()->toDateString(),
                'due_date_to' => $now->endOfDay()->toDateString(),
            ],
            'tomorrow' => [
                'due_date_from' => $now->addDay()->startOfDay()->toDateString(),
                'due_date_to' => $now->endOfDay()->toDateString(),
            ],
            'this_week' => [
                'due_date_from' => $now->startOfWeek()->toDateString(),
                'due_date_to' => $now->endOfWeek()->toDateString(),
            ],
            'next_week' => [
                'due_date_from' => $now->addWeek()->startOfWeek()->toDateString(),
                'due_date_to' => $now->endOfWeek()->toDateString(),
            ],
            'this_month' => [
                'due_date_from' => $now->startOfMonth()->toDateString(),
                'due_date_to' => $now->endOfMonth()->toDateString(),
            ],
            'next_month' => [
                'due_date_from' => $now->addMonth()->startOfMonth()->toDateString(),
                'due_date_to' => $now->endOfMonth()->toDateString(),
            ],
            'overdue' => [
                'due_date_to' => $now->subDay()->endOfDay()->toDateString(),
            ],
            default => null,
        };
    }

    /**
     * Get validated filters as an array
     *
     * @return array
     */
    public function getFilters(): array
    {
        $filters = [];
        
        // Basic filters
        if ($this->filled('status')) {
            $filters['status'] = $this->input('status');
        }
        
        if ($this->filled('priority')) {
            $filters['priority'] = $this->input('priority');
        }
        
        if ($this->has('parent_id')) {
            $filters['parent_id'] = $this->input('parent_id');
        }
        
        if ($this->filled('due_date_from')) {
            $filters['due_date_from'] = $this->input('due_date_from');
        }
        
        if ($this->filled('due_date_to')) {
            $filters['due_date_to'] = $this->input('due_date_to');
        }
        
        if ($this->filled('search')) {
            $filters['search'] = $this->input('search');
        }
        
        // Include options
        if ($this->filled('include_subtasks')) {
            $filters['include_subtasks'] = $this->boolean('include_subtasks');
        }
        
        if ($this->filled('include_completed')) {
            $filters['include_completed'] = $this->boolean('include_completed');
        }
        
        if ($this->filled('include_deleted')) {
            $filters['include_deleted'] = $this->boolean('include_deleted');
        }
        
        if ($this->filled('hierarchy_level')) {
            $filters['hierarchy_level'] = $this->input('hierarchy_level');
        }
        
        return $filters;
    }

    /**
     * Get pagination parameters
     *
     * @return array
     */
    public function getPagination(): array
    {
        return [
            'page' => $this->integer('page', 1),
            'per_page' => $this->integer('per_page', 15),
        ];
    }

    /**
     * Get sorting parameters
     *
     * @return array
     */
    public function getSorting(): array
    {
        return [
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
        ];
    }
}