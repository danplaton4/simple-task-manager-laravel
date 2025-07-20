<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Task Model Core', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('Basic Functionality', function () {
        it('can create a task', function () {
            $task = Task::factory()->for($this->user)->create();
            
            expect($task)->toBeInstanceOf(Task::class);
            expect($task->user_id)->toBe($this->user->id);
        });

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
    });

    describe('Status and Priority', function () {
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

        it('can check if task is completed', function () {
            $completedTask = Task::factory()->for($this->user)->create(['status' => 'completed']);
            $pendingTask = Task::factory()->for($this->user)->create(['status' => 'pending']);
            
            expect($completedTask->isCompleted())->toBeTrue();
            expect($pendingTask->isCompleted())->toBeFalse();
        });
    });

    describe('Scopes', function () {
        beforeEach(function () {
            $this->otherUser = User::factory()->create();
            
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
    });

    describe('Helper Methods', function () {
        it('can check if task is overdue', function () {
            $overdueTask = Task::factory()->for($this->user)->create([
                'due_date' => now()->subDay(),
                'status' => 'pending'
            ]);
            $futureTask = Task::factory()->for($this->user)->create([
                'due_date' => now()->addDay(),
                'status' => 'pending'
            ]);
            
            expect($overdueTask->isOverdue())->toBeTrue();
            expect($futureTask->isOverdue())->toBeFalse();
        });

        it('can check if task has subtasks', function () {
            $parentTask = Task::factory()->for($this->user)->create();
            Task::factory()->for($this->user)->create(['parent_id' => $parentTask->id]);
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