<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
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
        $taskId = $this->route('task');
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        
        return [
            // Multilingual name field
            'name' => $isUpdate ? 'sometimes|array' : 'required|array',
            'name.en' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'name.fr' => 'nullable|string|max:255',
            'name.de' => 'nullable|string|max:255',
            
            // Multilingual description field
            'description' => 'sometimes|nullable|array',
            'description.en' => 'nullable|string|max:1000',
            'description.fr' => 'nullable|string|max:1000',
            'description.de' => 'nullable|string|max:1000',
            
            // Status validation
            'status' => [
                $isUpdate ? 'sometimes' : 'required',
                Rule::in(Task::getStatuses())
            ],
            
            // Priority validation
            'priority' => [
                $isUpdate ? 'sometimes' : 'required',
                Rule::in(Task::getPriorities())
            ],
            
            // Due date validation
            'due_date' => 'sometimes|nullable|date|after:now',
            
            // Parent task validation
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where(function ($query) {
                    $query->where('user_id', Auth::id())
                          ->whereNull('deleted_at');
                }),
                function ($attribute, $value, $fail) use ($taskId) {
                    if ($value && $taskId && $value == $taskId) {
                        $fail('A task cannot be its own parent.');
                    }
                },
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
            'name.required' => 'The task name is required.',
            'name.array' => 'The task name must be provided as translations.',
            'name.en.required' => 'The English task name is required.',
            'name.en.string' => 'The English task name must be a string.',
            'name.en.max' => 'The English task name may not be greater than 255 characters.',
            'name.fr.string' => 'The French task name must be a string.',
            'name.fr.max' => 'The French task name may not be greater than 255 characters.',
            'name.de.string' => 'The German task name must be a string.',
            'name.de.max' => 'The German task name may not be greater than 255 characters.',
            
            'description.array' => 'The task description must be provided as translations.',
            'description.en.string' => 'The English task description must be a string.',
            'description.en.max' => 'The English task description may not be greater than 1000 characters.',
            'description.fr.string' => 'The French task description must be a string.',
            'description.fr.max' => 'The French task description may not be greater than 1000 characters.',
            'description.de.string' => 'The German task description must be a string.',
            'description.de.max' => 'The German task description may not be greater than 1000 characters.',
            
            'status.required' => 'The task status is required.',
            'status.in' => 'The selected status is invalid. Valid options are: ' . implode(', ', Task::getStatuses()),
            
            'priority.required' => 'The task priority is required.',
            'priority.in' => 'The selected priority is invalid. Valid options are: ' . implode(', ', Task::getPriorities()),
            
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after' => 'The due date must be in the future.',
            
            'parent_id.integer' => 'The parent task ID must be an integer.',
            'parent_id.exists' => 'The selected parent task does not exist or you do not have permission to access it.',
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
            'name.en' => 'English task name',
            'name.fr' => 'French task name',
            'name.de' => 'German task name',
            'description.en' => 'English task description',
            'description.fr' => 'French task description',
            'description.de' => 'German task description',
            'parent_id' => 'parent task',
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
            // Additional validation for parent task hierarchy
            if ($this->has('parent_id') && $this->parent_id) {
                $this->validateParentTaskHierarchy($validator);
            }
            
            // Validate multilingual fields have at least English
            if ($this->has('name') && is_array($this->name)) {
                if (empty($this->name['en'])) {
                    $validator->errors()->add('name.en', 'The English task name is required.');
                }
            }
        });
    }

    /**
     * Validate parent task hierarchy to prevent deep nesting and circular references
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateParentTaskHierarchy($validator): void
    {
        $parentTask = Task::where('id', $this->parent_id)
            ->where('user_id', Auth::id())
            ->first();
            
        if (!$parentTask) {
            return; // Will be caught by exists rule
        }
        
        // Check if parent is already a subtask (prevent deep nesting)
        if ($parentTask->isSubtask()) {
            $validator->errors()->add(
                'parent_id',
                'Cannot create subtask of a subtask. Maximum nesting level is 2.'
            );
        }
        
        // Check for circular references (if updating existing task)
        $taskId = $this->route('task');
        if ($taskId && $this->wouldCreateCircularReference($taskId, $this->parent_id)) {
            $validator->errors()->add(
                'parent_id',
                'This would create a circular reference in the task hierarchy.'
            );
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
        // Get all subtask IDs recursively
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Ensure parent_id is null if empty string is provided
        if ($this->has('parent_id') && $this->parent_id === '') {
            $this->merge(['parent_id' => null]);
        }
        
        // Clean up empty translation fields
        if ($this->has('name') && is_array($this->name)) {
            $name = array_filter($this->name, function ($value) {
                return !is_null($value) && $value !== '';
            });
            $this->merge(['name' => $name]);
        }
        
        if ($this->has('description') && is_array($this->description)) {
            $description = array_filter($this->description, function ($value) {
                return !is_null($value) && $value !== '';
            });
            $this->merge(['description' => empty($description) ? null : $description]);
        }
    }
}