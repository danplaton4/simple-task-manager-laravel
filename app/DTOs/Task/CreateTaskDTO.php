<?php

namespace App\DTOs\Task;

use App\DTOs\BaseDTO;
use App\Http\Requests\TaskRequest;
use App\Models\Task;
use Carbon\Carbon;

class CreateTaskDTO extends BaseDTO
{
    public function __construct(
        public readonly array $name,
        public readonly ?array $description,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?Carbon $dueDate,
        public readonly ?int $parentId
    ) {}

    /**
     * Create DTO from TaskRequest.
     */
    public static function fromRequest(TaskRequest $request): self
    {
        $validated = $request->validated();
        
        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            status: $validated['status'],
            priority: $validated['priority'],
            dueDate: isset($validated['due_date']) ? Carbon::parse($validated['due_date']) : null,
            parentId: $validated['parent_id'] ?? null
        );
    }

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            status: $data['status'],
            priority: $data['priority'],
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            parentId: $data['parent_id'] ?? null
        );
    }

    /**
     * Get the data ready for model creation.
     */
    public function toModelData(int $userId): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->dueDate,
            'parent_id' => $this->parentId,
            'user_id' => $userId,
        ];
    }

    /**
     * Validate the DTO data.
     */
    public function validate(): array
    {
        $errors = [];

        // Validate name (must be array with at least English)
        if (!is_array($this->name)) {
            $errors['name'] = 'Name must be provided as translations';
        } elseif (empty($this->name) || empty($this->name['en'])) {
            $errors['name.en'] = 'English name is required';
        }

        // Validate status
        if (!in_array($this->status, Task::getStatuses())) {
            $errors['status'] = 'Invalid status. Valid options are: ' . implode(', ', Task::getStatuses());
        }

        // Validate priority
        if (!in_array($this->priority, Task::getPriorities())) {
            $errors['priority'] = 'Invalid priority. Valid options are: ' . implode(', ', Task::getPriorities());
        }

        // Validate due date
        if ($this->dueDate && $this->dueDate->isPast()) {
            $errors['due_date'] = 'Due date must be in the future';
        }

        // Validate parent ID
        if ($this->parentId && $this->parentId <= 0) {
            $errors['parent_id'] = 'Parent ID must be a positive integer';
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
     * Get localized name for a specific locale.
     */
    public function getLocalizedName(string $locale = 'en'): string
    {
        return $this->name[$locale] ?? $this->name['en'] ?? '';
    }

    /**
     * Get localized description for a specific locale.
     */
    public function getLocalizedDescription(string $locale = 'en'): ?string
    {
        if (!$this->description) {
            return null;
        }
        
        return $this->description[$locale] ?? $this->description['en'] ?? null;
    }

    /**
     * Check if this is a subtask.
     */
    public function isSubtask(): bool
    {
        return $this->parentId !== null;
    }

    /**
     * Check if due date is set.
     */
    public function hasDueDate(): bool
    {
        return $this->dueDate !== null;
    }
}