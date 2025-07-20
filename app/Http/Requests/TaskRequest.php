<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization will be handled by policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taskId = $this->route('task') ? $this->route('task')->id : null;

        return [
            // Multilingual name validation
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'name.fr' => 'nullable|string|max:255',
            'name.de' => 'nullable|string|max:255',

            // Multilingual description validation
            'description' => 'nullable|array',
            'description.en' => 'nullable|string|max:1000',
            'description.fr' => 'nullable|string|max:1000',
            'description.de' => 'nullable|string|max:1000',

            // Status validation
            'status' => [
                'required',
                Rule::in(Task::getStatuses())
            ],

            // Priority validation
            'priority' => [
                'required',
                Rule::in(Task::getPriorities())
            ],

            // Due date validation
            'due_date' => 'nullable|date|after:now',

            // Parent task validation
            'parent_id' => [
                'nullable',
                'exists:tasks,id',
                function ($attribute, $value, $fail) use ($taskId) {
                    if ($value) {
                        // Prevent self-reference
                        if ($taskId && $value == $taskId) {
                            $fail('A task cannot be its own parent.');
                        }

                        // Prevent creating subtask of a subtask (max 2 levels)
                        $parentTask = Task::find($value);
                        if ($parentTask && $parentTask->isSubtask()) {
                            $fail('Cannot create subtask of a subtask. Maximum nesting level is 2.');
                        }

                        // Ensure parent belongs to the same user
                        if ($parentTask && $parentTask->user_id !== auth()->id()) {
                            $fail('Parent task must belong to the same user.');
                        }
                    }
                }
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Task name is required.',
            'name.en.required' => 'Task name in English is required.',
            'name.en.max' => 'Task name in English cannot exceed 255 characters.',
            'name.fr.max' => 'Task name in French cannot exceed 255 characters.',
            'name.de.max' => 'Task name in German cannot exceed 255 characters.',
            
            'description.en.max' => 'Task description in English cannot exceed 1000 characters.',
            'description.fr.max' => 'Task description in French cannot exceed 1000 characters.',
            'description.de.max' => 'Task description in German cannot exceed 1000 characters.',
            
            'status.required' => 'Task status is required.',
            'status.in' => 'Invalid task status. Must be one of: pending, in_progress, completed, cancelled.',
            
            'priority.required' => 'Task priority is required.',
            'priority.in' => 'Invalid task priority. Must be one of: low, medium, high, urgent.',
            
            'due_date.date' => 'Due date must be a valid date.',
            'due_date.after' => 'Due date must be in the future.',
            
            'parent_id.exists' => 'Selected parent task does not exist.',
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
            'name.en' => 'task name (English)',
            'name.fr' => 'task name (French)',
            'name.de' => 'task name (German)',
            'description.en' => 'task description (English)',
            'description.fr' => 'task description (French)',
            'description.de' => 'task description (German)',
            'parent_id' => 'parent task',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure name and description are arrays if they're not provided as such
        if ($this->has('name') && !is_array($this->name)) {
            $this->merge([
                'name' => ['en' => $this->name]
            ]);
        }

        if ($this->has('description') && !is_array($this->description)) {
            $this->merge([
                'description' => ['en' => $this->description]
            ]);
        }

        // Set user_id to current authenticated user
        $this->merge([
            'user_id' => auth()->id()
        ]);
    }
}
