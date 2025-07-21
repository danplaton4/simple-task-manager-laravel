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
        $supportedLocales = config('app.available_locales', ['en', 'fr', 'de']);
        
        $rules = [
            // Multilingual name field - array is required
            'name' => $isUpdate ? 'sometimes|array' : 'required|array',
            
            // Multilingual description field - optional array
            'description' => 'sometimes|nullable|array',
            
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

        // Add dynamic validation rules for each supported locale
        foreach ($supportedLocales as $locale => $name) {
            $localeKey = is_string($locale) ? $locale : $name;
            
            // Name validation per locale
            if ($localeKey === 'en') {
                // English is required
                $rules["name.{$localeKey}"] = $isUpdate 
                    ? 'sometimes|required|string|min:3|max:255' 
                    : 'required|string|min:3|max:255';
            } else {
                // Other languages are optional but must meet criteria if provided
                $rules["name.{$localeKey}"] = 'nullable|string|min:3|max:255';
            }
            
            // Description validation per locale (always optional)
            $rules["description.{$localeKey}"] = 'nullable|string|max:1000';
        }
        
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $supportedLocales = config('app.available_locales', ['en' => 'English', 'fr' => 'French', 'de' => 'German']);
        $messages = [
            // General field messages
            'name.required' => 'Task name translations are required.',
            'name.array' => 'Task name must be provided as translations for different languages.',
            'description.array' => 'Task description must be provided as translations for different languages.',
            
            // Status and priority messages
            'status.required' => 'Task status is required.',
            'status.in' => 'The selected status is invalid. Valid options are: ' . implode(', ', Task::getStatuses()),
            'priority.required' => 'Task priority is required.',
            'priority.in' => 'The selected priority is invalid. Valid options are: ' . implode(', ', Task::getPriorities()),
            
            // Date validation
            'due_date.date' => 'Due date must be a valid date.',
            'due_date.after' => 'Due date must be in the future.',
            
            // Parent task validation
            'parent_id.integer' => 'Parent task ID must be a valid number.',
            'parent_id.exists' => 'The selected parent task does not exist or you do not have permission to access it.',
        ];

        // Add dynamic messages for each supported locale
        foreach ($supportedLocales as $locale => $languageName) {
            $localeKey = is_string($locale) ? $locale : $languageName;
            $displayName = is_string($locale) ? $languageName : $locale;
            
            // Name field messages
            $messages["name.{$localeKey}.required"] = "Task name in {$displayName} is required.";
            $messages["name.{$localeKey}.string"] = "Task name in {$displayName} must be text.";
            $messages["name.{$localeKey}.min"] = "Task name in {$displayName} must be at least 3 characters.";
            $messages["name.{$localeKey}.max"] = "Task name in {$displayName} cannot exceed 255 characters.";
            
            // Description field messages
            $messages["description.{$localeKey}.string"] = "Task description in {$displayName} must be text.";
            $messages["description.{$localeKey}.max"] = "Task description in {$displayName} cannot exceed 1000 characters.";
        }
        
        return $messages;
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
            
            // Enhanced multilingual validation
            $this->validateTranslationCompleteness($validator);
            $this->validateTranslationConsistency($validator);
        });
    }

    /**
     * Validate translation completeness
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateTranslationCompleteness($validator): void
    {
        // Ensure English name is always provided (required language)
        if ($this->has('name') && is_array($this->name)) {
            if (empty($this->name['en']) || trim($this->name['en']) === '') {
                $validator->errors()->add('name.en', 'Task name in English is required as it serves as the default language.');
            }
        }
        
        // Validate that if any translation is provided, it meets minimum requirements
        $supportedLocales = array_keys(config('app.available_locales', ['en', 'fr', 'de']));
        
        foreach ($supportedLocales as $locale) {
            // Check name translations
            if ($this->has("name.{$locale}") && !empty($this->input("name.{$locale}"))) {
                $nameValue = trim($this->input("name.{$locale}"));
                if (strlen($nameValue) < 3) {
                    $validator->errors()->add("name.{$locale}", "Task name in " . strtoupper($locale) . " must be at least 3 characters if provided.");
                }
            }
            
            // Check description translations
            if ($this->has("description.{$locale}") && !empty($this->input("description.{$locale}"))) {
                $descValue = trim($this->input("description.{$locale}"));
                if (strlen($descValue) > 1000) {
                    $validator->errors()->add("description.{$locale}", "Task description in " . strtoupper($locale) . " cannot exceed 1000 characters.");
                }
            }
        }
    }

    /**
     * Validate translation consistency
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateTranslationConsistency($validator): void
    {
        // Check if name translations are provided as an array
        if ($this->has('name') && !is_array($this->name)) {
            $validator->errors()->add('name', 'Task name must be provided as translations for different languages.');
        }
        
        // Check if description translations are provided as an array (when provided)
        if ($this->has('description') && !is_null($this->description) && !is_array($this->description)) {
            $validator->errors()->add('description', 'Task description must be provided as translations for different languages when specified.');
        }
        
        // Warn about incomplete translations (informational)
        if ($this->has('name') && is_array($this->name)) {
            $supportedLocales = array_keys(config('app.available_locales', ['en', 'fr', 'de']));
            $providedLocales = array_filter($this->name, function($value) {
                return !empty(trim($value ?? ''));
            });
            
            $missingLocales = array_diff($supportedLocales, array_keys($providedLocales));
            
            // Only add info message if English is provided but other languages are missing
            if (!empty($this->name['en']) && !empty($missingLocales) && count($missingLocales) < count($supportedLocales)) {
                // This is informational - we don't add it as an error since other languages are optional
                // But we could log it or handle it differently if needed
            }
        }
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
        
        // Clean up and normalize translation fields
        $this->prepareTranslationFields();
    }

    /**
     * Prepare translation fields for validation
     *
     * @return void
     */
    private function prepareTranslationFields(): void
    {
        $supportedLocales = array_keys(config('app.available_locales', ['en', 'fr', 'de']));
        
        // Prepare name translations
        if ($this->has('name') && is_array($this->name)) {
            $name = [];
            foreach ($supportedLocales as $locale) {
                if (isset($this->name[$locale])) {
                    $value = trim($this->name[$locale] ?? '');
                    if ($value !== '') {
                        $name[$locale] = $value;
                    }
                }
            }
            $this->merge(['name' => empty($name) ? null : $name]);
        }
        
        // Prepare description translations
        if ($this->has('description') && is_array($this->description)) {
            $description = [];
            foreach ($supportedLocales as $locale) {
                if (isset($this->description[$locale])) {
                    $value = trim($this->description[$locale] ?? '');
                    if ($value !== '') {
                        $description[$locale] = $value;
                    }
                }
            }
            $this->merge(['description' => empty($description) ? null : $description]);
        }
        
        // Handle case where name or description is sent as a string (backward compatibility)
        if ($this->has('name') && is_string($this->name)) {
            $this->merge(['name' => ['en' => $this->name]]);
        }
        
        if ($this->has('description') && is_string($this->description)) {
            $this->merge(['description' => ['en' => $this->description]]);
        }
    }
}