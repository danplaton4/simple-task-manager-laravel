<?php

namespace App\Models;

use App\Services\LocaleCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Translatable\HasTranslations;

class Task extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'due_date',
        'parent_id',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'datetime',
        'name' => 'array',
        'description' => 'array',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'deleted_at',
    ];

    /**
     * Task status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Task priority constants.
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Get all available task statuses.
     *
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get all available task priorities.
     *
     * @return array<string>
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * Get the parent task that this task belongs to.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the subtasks for this task.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Get all subtasks recursively.
     */
    public function allSubtasks(): HasMany
    {
        return $this->subtasks()->with('allSubtasks');
    }

    /**
     * Get the user that owns the task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include tasks for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include tasks with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include tasks with a specific priority.
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include root tasks (no parent).
     */
    public function scopeRootTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include subtasks (has parent).
     */
    public function scopeSubtasks($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !$this->isCompleted();
    }

    /**
     * Check if the task has subtasks.
     */
    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    /**
     * Check if the task is a subtask.
     */
    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get the task's completion percentage based on subtasks.
     */
    public function getCompletionPercentage(): int
    {
        if (!$this->hasSubtasks()) {
            return $this->isCompleted() ? 100 : 0;
        }

        $subtasks = $this->subtasks;
        $completedSubtasks = $subtasks->where('status', self::STATUS_COMPLETED)->count();
        
        return $subtasks->count() > 0 ? (int) round(($completedSubtasks / $subtasks->count()) * 100) : 0;
    }

    /**
     * Get the task name in the current locale or fallback with caching.
     */
    public function getLocalizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try to get from cache first
        $cacheService = app(LocaleCacheService::class);
        $cachedData = $cacheService->getCachedTaskTranslation($this->id, $locale);
        
        if ($cachedData && isset($cachedData['name'])) {
            return $cachedData['name'];
        }
        
        // Get from database and cache it
        $name = $this->getTranslation('name', $locale) ?? $this->getTranslation('name', config('app.fallback_locale')) ?? '';
        
        // Cache the translation data
        $translationData = [
            'name' => $name,
            'description' => $this->getLocalizedDescription($locale),
            'cached_at' => now()->toISOString()
        ];
        $cacheService->cacheTaskTranslation($this->id, $locale, $translationData);
        
        return $name;
    }

    /**
     * Get the task description in the current locale or fallback with caching.
     */
    public function getLocalizedDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try to get from cache first
        $cacheService = app(LocaleCacheService::class);
        $cachedData = $cacheService->getCachedTaskTranslation($this->id, $locale);
        
        if ($cachedData && isset($cachedData['description'])) {
            return $cachedData['description'];
        }
        
        // Get from database
        $description = $this->getTranslation('description', $locale);
        
        if (empty($description)) {
            $description = $this->getTranslation('description', config('app.fallback_locale'));
        }
        
        return $description;
    }

    /**
     * Get all translations for a specific field.
     */
    public function getFieldTranslations(string $field): array
    {
        if (!in_array($field, $this->translatable)) {
            throw new \InvalidArgumentException("Field '{$field}' is not translatable.");
        }

        return $this->getTranslations($field);
    }

    /**
     * Check if a translation exists for a specific field and locale.
     */
    public function hasTranslation(string $field, string $locale): bool
    {
        if (!in_array($field, $this->translatable)) {
            return false;
        }

        $translations = $this->getTranslations($field);
        return isset($translations[$locale]) && !empty($translations[$locale]);
    }

    /**
     * Get available locales for a specific field.
     */
    public function getAvailableLocales(string $field): array
    {
        if (!in_array($field, $this->translatable)) {
            return [];
        }

        $translations = $this->getTranslations($field);
        return array_keys(array_filter($translations, function ($value) {
            return !empty($value);
        }));
    }

    /**
     * Get translation completion percentage for the task.
     */
    public function getTranslationCompleteness(): array
    {
        $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
        $completeness = [];

        foreach ($supportedLocales as $locale) {
            $hasName = $this->hasTranslation('name', $locale);
            $hasDescription = $this->hasTranslation('description', $locale);
            
            // Name is required, description is optional
            $completeness[$locale] = [
                'name' => $hasName,
                'description' => $hasDescription,
                'complete' => $hasName, // Only name is required for completeness
                'percentage' => $hasName ? 100 : 0,
            ];
        }

        return $completeness;
    }

    /**
     * Scope to filter tasks by locale availability.
     */
    public function scopeWithTranslation($query, string $locale, ?string $field = null)
    {
        if ($field && in_array($field, $this->translatable)) {
            return $query->whereJsonContains($field, [$locale => true]);
        }

        // Check if any translatable field has the locale
        $query->where(function ($q) use ($locale) {
            foreach ($this->translatable as $translatableField) {
                $q->orWhereJsonContains($translatableField, [$locale => true]);
            }
        });

        return $query;
    }

    /**
     * Scope for locale-specific search functionality.
     * Searches within the specified locale's content only.
     * Optimized to use database indexes for better performance.
     */
    public function scopeSearchInLocale($query, string $search, ?string $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $searchTerm = "%{$search}%";
        
        return $query->where(function ($q) use ($searchTerm, $locale, $fallbackLocale) {
            // Primary search in the specified locale (uses indexes)
            $q->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) AS CHAR(255)) LIKE ?", [$searchTerm])
              ->orWhereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) AS CHAR(500)) LIKE ?", [$searchTerm]);
            
            // If searching in a non-fallback locale, also search fallback as backup
            if ($locale !== $fallbackLocale) {
                $q->orWhere(function ($fallbackQuery) use ($searchTerm, $fallbackLocale, $locale) {
                    // Search fallback name when current locale name doesn't exist or is empty
                    $fallbackQuery->where(function ($nameQuery) use ($searchTerm, $fallbackLocale, $locale) {
                        $nameQuery->whereRaw("(JSON_EXTRACT(name, '$.{$locale}') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) = '')")
                                  ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$fallbackLocale}')) AS CHAR(255)) LIKE ?", [$searchTerm]);
                    })->orWhere(function ($descQuery) use ($searchTerm, $fallbackLocale, $locale) {
                        // Search fallback description when current locale description doesn't exist or is empty
                        $descQuery->whereRaw("(JSON_EXTRACT(description, '$.{$locale}') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$locale}')) = '')")
                                  ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(description, '$.{$fallbackLocale}')) AS CHAR(500)) LIKE ?", [$searchTerm]);
                    });
                });
            }
        });
    }

    /**
     * Get translation status for a specific locale with UI indicators and caching.
     */
    public function getTranslationStatusForLocale(string $locale): array
    {
        // Try to get from cache first
        $cacheService = app(LocaleCacheService::class);
        $cachedStatus = $cacheService->getCachedTranslationStatus($this->id);
        
        if ($cachedStatus && isset($cachedStatus[$locale])) {
            return $cachedStatus[$locale];
        }
        
        // Calculate status from database
        $fallbackLocale = config('app.fallback_locale', 'en');
        $hasName = $this->hasTranslation('name', $locale);
        $hasDescription = $this->hasTranslation('description', $locale);
        
        $status = [
            'locale' => $locale,
            'has_name' => $hasName,
            'has_description' => $hasDescription,
            'is_complete' => $hasName, // Name is required for completeness
            'fallback_name' => !$hasName ? $this->getTranslation('name', $fallbackLocale) : null,
            'fallback_description' => !$hasDescription ? $this->getTranslation('description', $fallbackLocale) : null,
            'fallback_used' => [
                'name' => !$hasName && $this->hasTranslation('name', $fallbackLocale),
                'description' => !$hasDescription && $this->hasTranslation('description', $fallbackLocale),
            ],
            'percentage' => $this->calculateLocaleCompletionPercentage($locale),
            'missing_fields' => $this->getMissingTranslationFields($locale),
        ];
        
        // Cache the status for all locales
        $allStatuses = $cachedStatus ?? [];
        $allStatuses[$locale] = $status;
        $cacheService->cacheTranslationStatus($this->id, $allStatuses);
        
        return $status;
    }

    /**
     * Calculate completion percentage for a specific locale.
     */
    private function calculateLocaleCompletionPercentage(string $locale): int
    {
        $totalFields = count($this->translatable);
        $requiredFields = ['name']; // Only name is required
        $optionalFields = array_diff($this->translatable, $requiredFields);
        
        $completedRequired = 0;
        $completedOptional = 0;
        
        foreach ($requiredFields as $field) {
            if ($this->hasTranslation($field, $locale)) {
                $completedRequired++;
            }
        }
        
        foreach ($optionalFields as $field) {
            if ($this->hasTranslation($field, $locale)) {
                $completedOptional++;
            }
        }
        
        // Required fields are weighted more heavily (70%), optional fields 30%
        $requiredWeight = 0.7;
        $optionalWeight = 0.3;
        
        $requiredPercentage = count($requiredFields) > 0 ? ($completedRequired / count($requiredFields)) : 1;
        $optionalPercentage = count($optionalFields) > 0 ? ($completedOptional / count($optionalFields)) : 1;
        
        return (int) round(($requiredPercentage * $requiredWeight + $optionalPercentage * $optionalWeight) * 100);
    }

    /**
     * Get missing translation fields for a specific locale.
     */
    private function getMissingTranslationFields(string $locale): array
    {
        $missing = [];
        
        foreach ($this->translatable as $field) {
            if (!$this->hasTranslation($field, $locale)) {
                $missing[] = [
                    'field' => $field,
                    'required' => $field === 'name', // Only name is required
                    'has_fallback' => $this->hasTranslation($field, config('app.fallback_locale', 'en')),
                ];
            }
        }
        
        return $missing;
    }

    /**
     * Get all locales that have at least one translation for this task.
     * Optimized for performance by checking all translatable fields at once.
     */
    public function getTranslatedLocales(): array
    {
        $translatedLocales = [];
        $supportedLocales = array_keys(config('app.available_locales', ['en' => 'English']));
        
        foreach ($supportedLocales as $locale) {
            $hasAnyTranslation = false;
            
            foreach ($this->translatable as $field) {
                if ($this->hasTranslation($field, $locale)) {
                    $hasAnyTranslation = true;
                    break;
                }
            }
            
            if ($hasAnyTranslation) {
                $translatedLocales[] = $locale;
            }
        }
        
        return $translatedLocales;
    }

    /**
     * Scope to filter tasks that have translations in a specific locale.
     * Optimized to use database indexes.
     */
    public function scopeHasTranslationInLocale($query, string $locale)
    {
        return $query->where(function ($q) use ($locale) {
            $q->whereRaw("JSON_EXTRACT(name, '$.{$locale}') IS NOT NULL")
              ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) != ''");
        });
    }

    /**
     * Scope to get tasks with complete translations in a specific locale.
     * A task is considered complete if it has at least a name translation.
     */
    public function scopeCompleteInLocale($query, string $locale)
    {
        return $query->whereRaw("JSON_EXTRACT(name, '$.{$locale}') IS NOT NULL")
                     ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.{$locale}')) != ''");
    }

    /**
     * Determine if this task can be the parent of the given potential child task.
     * Prevents circular references and deep nesting.
     */
    public function canBeParentOf(Task $potentialChild): bool
    {
        // Cannot be parent of itself
        if ($this->id === $potentialChild->id) {
            return false;
        }
        // Cannot be parent if already a subtask
        if ($this->isSubtask()) {
            return false;
        }
        // Prevent circular reference: check if potentialChild is an ancestor
        $parent = $this->parent;
        while ($parent) {
            if ($parent->id === $potentialChild->id) {
                return false;
            }
            $parent = $parent->parent;
        }
        return true;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Invalidate cache when task is saved or deleted
        static::saved(function ($task) {
            $cacheService = app(LocaleCacheService::class);
            $cacheService->invalidateTaskCache($task->id);
        });

        static::deleted(function ($task) {
            $cacheService = app(LocaleCacheService::class);
            $cacheService->invalidateTaskCache($task->id);
        });

        // Prevent circular references when setting parent
        static::saving(function ($task) {
            if ($task->parent_id && $task->parent_id === $task->id) {
                throw new \InvalidArgumentException('A task cannot be its own parent.');
            }

            // Prevent deep nesting by checking if parent is already a subtask
            if ($task->parent_id) {
                $parent = static::find($task->parent_id);
                if ($parent && $parent->isSubtask()) {
                    throw new \InvalidArgumentException('Cannot create subtask of a subtask. Maximum nesting level is 2.');
                }
            }

            // Ensure at least the fallback locale has a name
            if (empty($task->getTranslation('name', config('app.fallback_locale')))) {
                throw new \InvalidArgumentException('Task name is required in the fallback locale.');
            }
        });
    }
}
