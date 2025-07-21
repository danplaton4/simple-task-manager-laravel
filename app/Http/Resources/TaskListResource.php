<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskListResource extends JsonResource
{
    /**
     * Transform the resource into an array for task lists - minimal localized data.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        
        return [
            'id' => $this->id,
            'name' => $this->getLocalizedName($locale),
            'description' => $this->getLocalizedDescription($locale),
            'status' => $this->status,
            'status_label' => __("messages.task.status.{$this->status}"),
            'priority' => $this->priority,
            'priority_label' => __("messages.task.priority.{$this->priority}"),
            'due_date' => $this->due_date?->toISOString(),
            'parent_id' => $this->parent_id,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Computed fields
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $this->isOverdue(),
            'has_subtasks' => $this->hasSubtasks(),
            'is_subtask' => $this->isSubtask(),
            'completion_percentage' => $this->getCompletionPercentage(),
            
            // Translation status indicators
            'translation_status' => [
                'current_locale' => $locale,
                'has_translation' => $this->hasTranslation('name', $locale),
                'fallback_used' => !$this->hasTranslation('name', $locale),
                'available_locales' => $this->getAvailableLocales('name'),
                'completeness_percentage' => $this->getTranslationCompletenessPercentage(),
            ],
            
            // Essential metadata for list view
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
     * Get days until due date
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
     */
    private function getDaysOverdue(): ?int
    {
        if (!$this->due_date || !$this->isOverdue()) {
            return null;
        }
        
        return now()->diffInDays($this->due_date);
    }

    /**
     * Get overall translation completeness percentage
     */
    private function getTranslationCompletenessPercentage(): int
    {
        $completeness = $this->getTranslationCompleteness();
        $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
        
        if (empty($supportedLocales)) {
            return 100;
        }
        
        $completeCount = 0;
        foreach ($supportedLocales as $locale) {
            if (isset($completeness[$locale]) && $completeness[$locale]['complete']) {
                $completeCount++;
            }
        }
        
        return (int) round(($completeCount / count($supportedLocales)) * 100);
    }

    /**
     * Additional resource data when the resource is being used in a collection
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'locale' => app()->getLocale(),
                'available_locales' => array_keys(config('app.available_locales', ['en' => 'English'])),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}