<?php

namespace App\DTOs\Task;

use App\DTOs\BaseDTO;
use App\Http\Requests\TaskRequest;
use App\Models\Task;
use Carbon\Carbon;

class UpdateTaskDTO extends BaseDTO
{
    public function __construct(
        public readonly ?array $name = null,
        public readonly ?array $description = null,
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly ?Carbon $dueDate = null,
        public readonly ?int $parentId = null,
        public readonly bool $clearDueDate = false,
        public readonly bool $clearParent = false
    ) {}

    /**
     * Create DTO from TaskRequest.
     */
    public static function fromRequest(TaskRequest $request): self
    {
        $validated = $request->validated();
        
        return new self(
            name: $validated['name'] ?? null,
            description: $validated['description'] ?? null,
            status: $validated['status'] ?? null,
            priority: $validated['priority'] ?? null,
            dueDate: isset($validated['due_date']) ? Carbon::parse($validated['due_date']) : null,
            parentId: $validated['parent_id'] ?? null,
            clearDueDate: array_key_exists('due_date', $validated) && $validated['due_date'] === null,
            clearParent: array_key_exists('parent_id', $validated) && $validated['parent_id'] === null
        );
    }

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            status: $data['status'] ?? null,
            priority: $data['priority'] ?? null,
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            parentId: $data['parent_id'] ?? null,
            clearDueDate: array_key_exists('due_date', $data) && $data['due_date'] === null,
            clearParent: array_key_exists('parent_id', $data) && $data['parent_id'] === null
        );
    }

    /**
     * Get the data ready for model update.
     */
    public function toModelData(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        if ($this->priority !== null) {
            $data['priority'] = $this->priority;
        }

        if ($this->dueDate !== null || $this->clearDueDate) {
            $data['due_date'] = $this->dueDate;
        }

        if ($this->parentId !== null || $this->clearParent) {
            $data['parent_id'] = $this->parentId;
        }

        return $data;
    }

    /**
     * Validate the DTO data.
     */
    public function validate(): array
    {
        $errors = [];

        // Validate name if provided
        if ($this->name !== null) {
            if (!is_array($this->name)) {
                $errors['name'] = 'Name must be provided as translations';
            } elseif (empty($this->name['en'])) {
                $errors['name.en'] = 'English name is required';
            }
        }

        // Validate status if provided
        if ($this->status !== null && !in_array($this->status, Task::getStatuses())) {
            $errors['status'] = 'Invalid status. Valid options are: ' . implode(', ', Task::getStatuses());
        }

        // Validate priority if provided
        if ($this->priority !== null && !in_array($this->priority, Task::getPriorities())) {
            $errors['priority'] = 'Invalid priority. Valid options are: ' . implode(', ', Task::getPriorities());
        }

        // Validate due date if provided
        if ($this->dueDate && $this->dueDate->isPast()) {
            $errors['due_date'] = 'Due date must be in the future';
        }

        // Validate parent ID if provided
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
     * Check if any field is being updated.
     */
    public function hasUpdates(): bool
    {
        return $this->name !== null ||
               $this->description !== null ||
               $this->status !== null ||
               $this->priority !== null ||
               $this->dueDate !== null ||
               $this->parentId !== null ||
               $this->clearDueDate ||
               $this->clearParent;
    }

    /**
     * Get list of fields being updated.
     */
    public function getUpdatedFields(): array
    {
        $fields = [];

        if ($this->name !== null) $fields[] = 'name';
        if ($this->description !== null) $fields[] = 'description';
        if ($this->status !== null) $fields[] = 'status';
        if ($this->priority !== null) $fields[] = 'priority';
        if ($this->dueDate !== null || $this->clearDueDate) $fields[] = 'due_date';
        if ($this->parentId !== null || $this->clearParent) $fields[] = 'parent_id';

        return $fields;
    }

    /**
     * Get localized name for a specific locale.
     */
    public function getLocalizedName(string $locale = 'en'): ?string
    {
        if (!$this->name) {
            return null;
        }
        
        return $this->name[$locale] ?? $this->name['en'] ?? null;
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
     * Check if status is being changed to completed.
     */
    public function isBeingCompleted(): bool
    {
        return $this->status === Task::STATUS_COMPLETED;
    }

    /**
     * Check if parent is being changed.
     */
    public function isParentChanging(): bool
    {
        return $this->parentId !== null || $this->clearParent;
    }
}