<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Task Model', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('Relationships', function () {
        it('belongs to a user', function () {
            $task = Task::factory()->for($this->user)->create();
            
            expect($task->user)->toBeInstanceOf(User::class);
            expect($task->user->id)->toBe($this->user->id);
        });

        it('can have a parent task', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            $childTask = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            expect($childTask->parent)->toBeInstanceOf(Task::class);
            expect($childTask->parent->id)->toBe($parentTask->id);
        });

        it('can have subtasks', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            $subtask1 = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            $subtask2 = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            expect($parentTask->subtasks)->toHaveCount(2);
            expect($parentTask->subtasks->pluck('id')->toArray())->toContain($subtask1->id, $subtask2->id);
        });

        it('can get all subtasks recursively', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            $subtask = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            $allSubtasks = $parentTask->allSubtasks;
            
            expect($allSubtasks)->toHaveCount(1);
            expect($allSubtasks->first()->id)->toBe($subtask->id);
        });
    });

    describe('Scopes', function () {
        beforeEach(function () {
            $this->otherUser = User::factory()->create();
            
            // Create tasks for different users
            Task::factory()->for($this->user)->create(['status' => 'pending', 'priority' => 'high']);
            Task::factory()->for($this->user)->create(['status' => 'completed', 'priority' => 'low']);
            Task::factory()->for($this->otherUser)->create(['status' => 'pending', 'priority' => 'high']);
        });

        it('can scope tasks for a specific user', function () {
            $userTasks = Task::forUser($this->user->id)->get();
            
            expect($userTasks)->toHaveCount(2);
            expect($userTasks->every(fn($task) => $task->user_id === $this->user->id))->toBeTrue();
        });

        it('can scope tasks by status', function () {
            $pendingTasks = Task::withStatus('pending')->get();
            
            expect($pendingTasks)->toHaveCount(2);
            expect($pendingTasks->every(fn($task) => $task->status === 'pending'))->toBeTrue();
        });

        it('can scope tasks by priority', function () {
            $highPriorityTasks = Task::withPriority('high')->get();
            
            expect($highPriorityTasks)->toHaveCount(2);
            expect($highPriorityTasks->every(fn($task) => $task->priority === 'high'))->toBeTrue();
        });

        it('can scope root tasks', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            $rootTasks = Task::rootTasks()->get();
            
            expect($rootTasks->every(fn($task) => is_null($task->parent_id)))->toBeTrue();
        });

        it('can scope subtasks', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            $subtasks = Task::subtasks()->get();
            
            expect($subtasks->every(fn($task) => !is_null($task->parent_id)))->toBeTrue();
        });
    });

    describe('Status and Priority Constants', function () {
        it('has correct status constants', function () {
            expect(Task::STATUS_PENDING)->toBe('pending');
            expect(Task::STATUS_IN_PROGRESS)->toBe('in_progress');
            expect(Task::STATUS_COMPLETED)->toBe('completed');
            expect(Task::STATUS_CANCELLED)->toBe('cancelled');
        });

        it('has correct priority constants', function () {
            expect(Task::PRIORITY_LOW)->toBe('low');
            expect(Task::PRIORITY_MEDIUM)->toBe('medium');
            expect(Task::PRIORITY_HIGH)->toBe('high');
            expect(Task::PRIORITY_URGENT)->toBe('urgent');
        });

        it('returns all available statuses', function () {
            $statuses = Task::getStatuses();
            
            expect($statuses)->toContain('pending', 'in_progress', 'completed', 'cancelled');
            expect($statuses)->toHaveCount(4);
        });

        it('returns all available priorities', function () {
            $priorities = Task::getPriorities();
            
            expect($priorities)->toContain('low', 'medium', 'high', 'urgent');
            expect($priorities)->toHaveCount(4);
        });
    });

    describe('Helper Methods', function () {
        it('can check if task is completed', function () {
            $completedTask = Task::factory()->for($this->user)->create(['status' => 'completed']);
            $pendingTask = Task::factory()->for($this->user)->create(['status' => 'pending']);
            
            expect($completedTask->isCompleted())->toBeTrue();
            expect($pendingTask->isCompleted())->toBeFalse();
        });

        it('can check if task is overdue', function () {
            $overdueTask = Task::factory()->for($this->user)->create([
                'due_date' => now()->subDay(),
                'status' => 'pending'
            ]);
            $futureTask = Task::factory()->for($this->user)->create([
                'due_date' => now()->addDay(),
                'status' => 'pending'
            ]);
            $completedOverdueTask = Task::factory()->for($this->user)->create([
                'due_date' => now()->subDay(),
                'status' => 'completed'
            ]);
            
            expect($overdueTask->isOverdue())->toBeTrue();
            expect($futureTask->isOverdue())->toBeFalse();
            expect($completedOverdueTask->isOverdue())->toBeFalse();
        });

        it('can check if task has subtasks', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            $childTask = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            $standaloneTask = Task::factory()->for($this->user)->create();
            
            expect($parentTask->hasSubtasks())->toBeTrue();
            expect($standaloneTask->hasSubtasks())->toBeFalse();
        });

        it('can check if task is a subtask', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            $childTask = Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
            
            expect($childTask->isSubtask())->toBeTrue();
            expect($parentTask->isSubtask())->toBeFalse();
        });

        it('can calculate completion percentage based on subtasks', function () {
            $parentTask = Task::factory()->for($this->user)->create(['status' => 'pending']);
            
            // Task without subtasks
            expect($parentTask->getCompletionPercentage())->toBe(0);
            
            // Create subtasks
            Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id, 'status' => 'completed']);
            Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id, 'status' => 'pending']);
            
            // Refresh to load subtasks
            $parentTask->refresh();
            
            expect($parentTask->getCompletionPercentage())->toBe(50);
        });
    });

    describe('Multilingual Support', function () {
        beforeEach(function () {
            $this->task = Task::factory()->for($this->user)->create([
                'name' => [
                    'en' => 'English Task',
                    'fr' => 'Tâche Française',
                    'de' => 'Deutsche Aufgabe'
                ],
                'description' => [
                    'en' => 'English Description',
                    'fr' => 'Description Française'
                ]
            ]);
        });

        it('can get localized name', function () {
            expect($this->task->getLocalizedName('en'))->toBe('English Task');
            expect($this->task->getLocalizedName('fr'))->toBe('Tâche Française');
            expect($this->task->getLocalizedName('de'))->toBe('Deutsche Aufgabe');
        });

        it('falls back to default locale for missing translations', function () {
            config(['app.fallback_locale' => 'en']);
            
            expect($this->task->getLocalizedDescription('de'))->toBe('English Description');
        });

        it('can set translations for specific fields', function () {
            $this->task->setTranslation('name', 'es', 'Tarea Española');
            
            expect($this->task->getTranslation('name', 'es'))->toBe('Tarea Española');
        });

        it('throws exception when setting translation for non-translatable field', function () {
            $task = $this->task;
            expect(fn() => $task->setTranslation('status', 'fr', 'terminé'))
                ->toThrow(InvalidArgumentException::class, "Field 'status' is not translatable.");
        });

        it('can get all translations for a field', function () {
            $nameTranslations = $this->task->getFieldTranslations('name');
            
            expect($nameTranslations)->toHaveKey('en', 'English Task');
            expect($nameTranslations)->toHaveKey('fr', 'Tâche Française');
            expect($nameTranslations)->toHaveKey('de', 'Deutsche Aufgabe');
        });

        it('can check if translation exists', function () {
            expect($this->task->hasTranslation('name', 'en'))->toBeTrue();
            expect($this->task->hasTranslation('name', 'es'))->toBeFalse();
            expect($this->task->hasTranslation('description', 'de'))->toBeFalse();
        });

        it('can get available locales for a field', function () {
            $nameLocales = $this->task->getAvailableLocales('name');
            $descriptionLocales = $this->task->getAvailableLocales('description');
            
            expect($nameLocales)->toContain('en', 'fr', 'de');
            expect($descriptionLocales)->toContain('en', 'fr');
            expect($descriptionLocales)->not->toContain('de');
        });

        it('can get translation completeness', function () {
            config(['app.available_locales' => ['en' => 'English', 'fr' => 'French', 'de' => 'German']]);
            
            $completeness = $this->task->getTranslationCompleteness();
            
            expect($completeness['en']['complete'])->toBeTrue();
            expect($completeness['fr']['complete'])->toBeTrue();
            expect($completeness['de']['complete'])->toBeTrue();
            expect($completeness['en']['percentage'])->toBe(100);
        });
    });

    describe('Validation and Constraints', function () {
        it('prevents task from being its own parent', function () {
            $task = Task::factory()->for($this->user)->create();
            
            expect(function () use ($task) {
                $task->parent_id = $task->id;
                $task->save();
            })->toThrow(InvalidArgumentException::class, 'A task cannot be its own parent.');
        });

        it('prevents deep nesting beyond 2 levels', function () {
            $grandparent = Task::factory()->for($this->user)->create();
            $parent = Task::factory()->for($this->user)->create(['parent_id' => $grandparent->id]);
            $user = $this->user;
            
            expect(function () use ($parent, $user) {
                Task::factory()->for($user)->create(['parent_id' => $parent->id]);
            })->toThrow(InvalidArgumentException::class, 'Cannot create subtask of a subtask. Maximum nesting level is 2.');
        });

        it('requires name in fallback locale', function () {
            config(['app.fallback_locale' => 'en']);
            
            expect(function () {
                Task::factory()->for($this->user)->create([
                    'name' => ['fr' => 'Tâche Française'] // Missing English name
                ]);
            })->toThrow(InvalidArgumentException::class, 'Task name is required in the fallback locale.');
        });
    });

    describe('Soft Deletes', function () {
        it('soft deletes tasks', function () {
            $task = Task::factory()->for($this->user)->create();
            
            $task->delete();
            
            expect($task->trashed())->toBeTrue();
            expect(Task::count())->toBe(0);
            expect(Task::withTrashed()->count())->toBe(1);
        });

        it('can restore soft deleted tasks', function () {
            $task = Task::factory()->for($this->user)->create();
            $task->delete();
            
            $task->restore();
            
            expect($task->trashed())->toBeFalse();
            expect(Task::count())->toBe(1);
        });
    });
});