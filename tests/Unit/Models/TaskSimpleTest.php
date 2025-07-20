<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Task Model Simple', function () {
    it('can create a task', function () {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        
        expect($task)->toBeInstanceOf(Task::class);
        expect($task->user_id)->toBe($user->id);
    });

    it('has correct status constants', function () {
        expect(Task::STATUS_PENDING)->toBe('pending');
        expect(Task::STATUS_IN_PROGRESS)->toBe('in_progress');
        expect(Task::STATUS_COMPLETED)->toBe('completed');
        expect(Task::STATUS_CANCELLED)->toBe('cancelled');
    });
});