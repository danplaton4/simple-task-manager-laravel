<?php

use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;

test('user can view own task', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $task = new Task();
    $task->user_id = 1;

    expect($policy->view($user, $task))->toBeTrue();
});

test('user cannot view other users task', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $task = new Task();
    $task->user_id = 2;

    expect($policy->view($user1, $task))->toBeFalse();
});

test('user can create tasks', function () {
    $policy = new TaskPolicy();
    $user = new User();
    $user->id = 1;

    expect($policy->create($user))->toBeTrue();
});

test('user can update own task', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $task = new Task();
    $task->user_id = 1;

    expect($policy->update($user, $task))->toBeTrue();
});

test('user cannot update other users task', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $task = new Task();
    $task->user_id = 2;

    expect($policy->update($user1, $task))->toBeFalse();
});

test('user can delete own task', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $task = new Task();
    $task->user_id = 1;

    expect($policy->delete($user, $task))->toBeTrue();
});

test('user cannot delete other users task', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $task = new Task();
    $task->user_id = 2;

    expect($policy->delete($user1, $task))->toBeFalse();
});

test('user can restore own task', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $task = new Task();
    $task->user_id = 1;

    expect($policy->restore($user, $task))->toBeTrue();
});

test('user cannot restore other users task', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $task = new Task();
    $task->user_id = 2;

    expect($policy->restore($user1, $task))->toBeFalse();
});

test('user can manage subtasks of own task', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $task = new Task();
    $task->user_id = 1;

    expect($policy->manageSubtasks($user, $task))->toBeTrue();
});

test('user cannot manage subtasks of other users task', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $task = new Task();
    $task->user_id = 2;

    expect($policy->manageSubtasks($user1, $task))->toBeFalse();
});

test('user can assign own task as parent', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $parentTask = new Task();
    $parentTask->user_id = 1;

    expect($policy->assignParent($user, $parentTask))->toBeTrue();
});

test('user cannot assign other users task as parent', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $parentTask = new Task();
    $parentTask->user_id = 2;

    expect($policy->assignParent($user1, $parentTask))->toBeFalse();
});

test('user can view any tasks', function () {
    $policy = new TaskPolicy();
    $user = new User();
    $user->id = 1;

    expect($policy->viewAny($user))->toBeTrue();
});

test('user can force delete own task', function () {
    $policy = new TaskPolicy();
    
    $user = new User();
    $user->id = 1;
    $task = new Task();
    $task->user_id = 1;

    expect($policy->forceDelete($user, $task))->toBeTrue();
});

test('user cannot force delete other users task', function () {
    $policy = new TaskPolicy();
    
    $user1 = new User();
    $user1->id = 1;
    $task = new Task();
    $task->user_id = 2;

    expect($policy->forceDelete($user1, $task))->toBeFalse();
});