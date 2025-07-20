<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        
        return [
            'id' => $this->id,
            'name' => $this->getTranslatedName($locale),
            'name_translations' => $this->name,
            'description' => $this->getTranslatedDescription($locale),
            'description_translations' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->toISOString(),
            'parent_id' => $this->parent_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Computed fields
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $this->isOverdue(),
            'has_subtasks' => $this->hasSubtasks(),
            'is_subtask' => $this->isSubtask(),
            'completion_percentage' => $this->getCompletionPercentage(),
            
            // Relationships
            'parent' => new TaskResource($this->whenLoaded('parent')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Additional metadata
            'meta' => [
                'days_until_due' => $this->getDaysUntilDue(),
                'days_overdue' => $this->getDaysOverdue(),
                'subtask_count' => $this->whenLoaded('subtasks', function () {
                    return $this->subtasks->count();
                }),
                'completed_subtasks_count' => $this->whenLoaded('subtasks', function () {
                    return $this->subtasks->where('status', 'completed')->count();
                }),
            ],
        ];
    }

    /**
     * Get translated name for the current locale
     *
     * @param string $locale
     * @return string|null
     */
    private function getTranslatedName(string $locale): ?string
    {
        if (is_array($this->name)) {
            return $this->name[$locale] ?? $this->name['en'] ?? null;
        }
        
        return $this->name;
    }

    /**
     * Get translated description for the current locale
     *
     * @param string $locale
     * @return string|null
     */
    private function getTranslatedDescription(string $locale): ?string
    {
        if (is_array($this->description)) {
            return $this->description[$locale] ?? $this->description['en'] ?? null;
        }
        
        return $this->description;
    }

    /**
     * Get days until due date
     *
     * @return int|null
     */
    private function getDaysUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }
        
        $days = now()->diffInDays($this->due_date, false);
        
        return $days > 0 ? $days : null;
    }

    /**
     * Get days overdue
     *
     * @return int|null
     */
    private function getDaysOverdue(): ?int
    {
        if (!$this->due_date || !$this->isOverdue()) {
            return null;
        }
        
        return now()->diffInDays($this->due_date);
    }

    /**
     * Additional resource data when the resource is being used in a collection
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'locale' => app()->getLocale(),
                'available_locales' => ['en', 'fr', 'de'],
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}