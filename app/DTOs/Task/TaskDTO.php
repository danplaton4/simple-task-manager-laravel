<?php

namespace App\DTOs\Task;

use App\Models\Task;

class TaskDTO
{
    public int $id;
    public array $name;
    public ?array $description;
    public string $status;
    public string $priority;
    public ?string $dueDate;
    public ?int $parentId;
    public int $userId;

    public function __construct(Task $task)
    {
        $this->id = $task->id;
        $this->name = $task->getTranslations('name');
        $this->description = $task->getTranslations('description');
        $this->status = $task->status;
        $this->priority = $task->priority;
        $this->dueDate = $task->due_date?->toISOString();
        $this->parentId = $task->parent_id;
        $this->userId = $task->user_id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->dueDate,
            'parent_id' => $this->parentId,
            'user_id' => $this->userId,
        ];
    }
} 