<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array for task details/editing - full translation data.
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
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Computed fields
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $this->isOverdue(),
            'has_subtasks' => $this->hasSubtasks(),
            'is_subtask' => $this->isSubtask(),
            'completion_percentage' => $this->getCompletionPercentage(),
            
            // Full translation data for editing
            'translations' => [
                'name' => $this->getFieldTranslations('name'),
                'description' => $this->getFieldTranslations('description'),
            ],
            
            // Detailed translation information
            'translation_completeness' => $this->getTranslationCompleteness(),
            'available_locales' => $this->getAvailableLocales('name'),
            
            // Translation metadata
            'translation_info' => [
                'current_locale' => $locale,
                'supported_locales' => config('app.available_locales', ['en' => 'English']),
                'fallback_locale' => config('app.fallback_locale', 'en'),
                'has_translation' => [
                    'name' => $this->hasTranslation('name', $locale),
                    'description' => $this->hasTranslation('description', $locale),
                ],
                'fallback_used' => [
                    'name' => !$this->hasTranslation('name', $locale),
                    'description' => !$this->hasTranslation('description', $locale),
                ],
            ],
            
            // Relationships (when loaded)
            'parent' => new TaskListResource($this->whenLoaded('parent')),
            'subtasks' => TaskListResource::collection($this->whenLoaded('subtasks')),
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Detailed metadata
            'meta' => [
                'days_until_due' => $this->getDaysUntilDue(),
                'days_overdue' => $this->getDaysOverdue(),
                'subtask_count' => $this->whenLoaded('subtasks', function () {
                    return $this->subtasks->count();
                }),
                'completed_subtasks_count' => $this->whenLoaded('subtasks', function () {
                    return $this->subtasks->where('status', 'completed')->count();
                }),
                'translation_statistics' => $this->getTranslationStatistics(),
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
     * Get detailed translation statistics
     */
    private function getTranslationStatistics(): array
    {
        $completeness = $this->getTranslationCompleteness();
        $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
        
        $stats = [
            'total_locales' => count($supportedLocales),
            'complete_locales' => 0,
            'incomplete_locales' => 0,
            'missing_name_locales' => [],
            'missing_description_locales' => [],
            'overall_percentage' => 0,
        ];
        
        foreach ($supportedLocales as $locale) {
            if (isset($completeness[$locale])) {
                if ($completeness[$locale]['complete']) {
                    $stats['complete_locales']++;
                } else {
                    $stats['incomplete_locales']++;
                }
                
                if (!$completeness[$locale]['name']) {
                    $stats['missing_name_locales'][] = $locale;
                }
                
                if (!$completeness[$locale]['description']) {
                    $stats['missing_description_locales'][] = $locale;
                }
            }
        }
        
        $stats['overall_percentage'] = $stats['total_locales'] > 0 
            ? (int) round(($stats['complete_locales'] / $stats['total_locales']) * 100)
            : 100;
        
        return $stats;
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
                'supported_locales' => config('app.available_locales', ['en' => 'English']),
                'fallback_locale' => config('app.fallback_locale', 'en'),
                'timestamp' => now()->toISOString(),
                'include_translations' => true,
            ],
        ];
    }
}