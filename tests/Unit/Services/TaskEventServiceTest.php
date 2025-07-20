<?php

use App\Models\Task;
use App\Models\User;
use App\Services\TaskEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

describe('TaskEventService', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->service = new TaskEventService();
        
        // Mock Redis to avoid actual pub/sub operations in tests
        Redis::spy();
        Log::spy();
    });

    describe('Task Event Broadcasting', function () {
        beforeEach(function () {
            $this->task = Task::factory()->for($this->user)->create([
                'name' => ['en' => 'Test Task'],
                'description' => ['en' => 'Test Description'],
                'status' => 'pending',
                'priority' => 'medium'
            ]);
        });

        it('broadcasts task created event', function () {
            $this->service->broadcastTaskCreated($this->task);
            
            Redis::shouldHaveReceived('publish')
                ->twice() // Once for user channel, once for global channel
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'created' && 
                           $data['task']['id'] === $this->task->id;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Task created event broadcasted', [
                    'task_id' => $this->task->id,
                    'user_id' => $this->user->id
                ]);
        });

        it('broadcasts task updated event', function () {
            $changes = ['status' => 'completed'];
            
            $this->service->broadcastTaskUpdated($this->task, $changes);
            
            Redis::shouldHaveReceived('publish')
                ->twice()
                ->withArgs(function ($channel, $message) use ($changes) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'updated' && 
                           $data['task']['id'] === $this->task->id &&
                           $data['changes'] === $changes;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Task updated event broadcasted', [
                    'task_id' => $this->task->id,
                    'user_id' => $this->user->id,
                    'changes' => ['status']
                ]);
        });

        it('broadcasts task completed event', function () {
            $this->service->broadcastTaskCompleted($this->task);
            
            Redis::shouldHaveReceived('publish')
                ->twice()
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'completed' && 
                           $data['task']['id'] === $this->task->id;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Task completed event broadcasted', [
                    'task_id' => $this->task->id,
                    'user_id' => $this->user->id
                ]);
        });

        it('broadcasts task deleted event', function () {
            $this->service->broadcastTaskDeleted($this->task);
            
            Redis::shouldHaveReceived('publish')
                ->twice()
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'deleted' && 
                           $data['task']['id'] === $this->task->id;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Task deleted event broadcasted', [
                    'task_id' => $this->task->id,
                    'user_id' => $this->user->id
                ]);
        });

        it('broadcasts task restored event', function () {
            $this->service->broadcastTaskRestored($this->task);
            
            Redis::shouldHaveReceived('publish')
                ->twice()
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'restored' && 
                           $data['task']['id'] === $this->task->id;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Task restored event broadcasted', [
                    'task_id' => $this->task->id,
                    'user_id' => $this->user->id
                ]);
        });
    });

    describe('Hierarchical Task Events', function () {
        beforeEach(function () {
            $this->parentTask = Task::factory()->for($this->user)->create();
            $this->subtask1 = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);
            $this->subtask2 = Task::factory()->for($this->user)->create(['parent_id' => $this->parentTask->id]);
        });

        it('broadcasts subtask parent updated event', function () {
            $this->service->broadcastSubtaskParentUpdated($this->parentTask);
            
            // Should publish events for each subtask
            Redis::shouldHaveReceived('publish')
                ->twice() // Once for each subtask
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'parent_updated' && 
                           isset($data['parent_task']) &&
                           $data['parent_task']['id'] === $this->parentTask->id;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Subtask parent updated events broadcasted', [
                    'parent_task_id' => $this->parentTask->id,
                    'subtask_count' => 2
                ]);
        });

        it('broadcasts parent subtask updated event', function () {
            $this->service->broadcastParentSubtaskUpdated($this->subtask1);
            
            Redis::shouldHaveReceived('publish')
                ->once()
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'subtask_updated' && 
                           $data['task']['id'] === $this->parentTask->id &&
                           $data['updated_subtask']['id'] === $this->subtask1->id;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('Parent subtask updated event broadcasted', [
                    'parent_task_id' => $this->parentTask->id,
                    'subtask_id' => $this->subtask1->id
                ]);
        });

        it('does not broadcast parent subtask updated for root tasks', function () {
            $rootTask = Task::factory()->for($this->user)->create();
            
            $this->service->broadcastParentSubtaskUpdated($rootTask);
            
            Redis::shouldNotHaveReceived('publish');
            Log::shouldNotHaveReceived('info');
        });

        it('handles missing parent task gracefully', function () {
            $orphanTask = Task::factory()->for($this->user)->create(['parent_id' => 99999]);
            
            $this->service->broadcastParentSubtaskUpdated($orphanTask);
            
            Redis::shouldNotHaveReceived('publish');
            Log::shouldNotHaveReceived('info');
        });
    });

    describe('User Statistics Events', function () {
        it('broadcasts user statistics updated event', function () {
            $stats = [
                'total' => 10,
                'completed' => 5,
                'pending' => 3,
                'in_progress' => 2
            ];
            
            $this->service->broadcastUserStatsUpdated($this->user->id, $stats);
            
            Redis::shouldHaveReceived('publish')
                ->once()
                ->withArgs(function ($channel, $message) use ($stats) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'user_stats_updated' && 
                           $data['user_id'] === $this->user->id &&
                           $data['stats'] === $stats;
                });
            
            Log::shouldHaveReceived('info')
                ->once()
                ->with('User stats updated event broadcasted', [
                    'user_id' => $this->user->id
                ]);
        });
    });

    describe('Channel Management', function () {
        it('gets user channel subscribers count', function () {
            Redis::shouldReceive('pubsub')
                ->with('numsub', 'user_task_events:' . $this->user->id)
                ->andReturn(['channel', 5]);
            
            $count = $this->service->getUserChannelSubscribers($this->user->id);
            
            expect($count)->toBe(5);
        });

        it('handles redis errors when getting user channel subscribers', function () {
            Redis::shouldReceive('pubsub')
                ->andThrow(new Exception('Redis error'));
            
            $count = $this->service->getUserChannelSubscribers($this->user->id);
            
            expect($count)->toBe(0);
            Log::shouldHaveReceived('error')
                ->once()
                ->with('Failed to get user channel subscribers', [
                    'user_id' => $this->user->id,
                    'error' => 'Redis error'
                ]);
        });

        it('gets global channel subscribers count', function () {
            Redis::shouldReceive('pubsub')
                ->with('numsub', 'global_task_events')
                ->andReturn(['channel', 10]);
            
            $count = $this->service->getGlobalChannelSubscribers();
            
            expect($count)->toBe(10);
        });

        it('handles redis errors when getting global channel subscribers', function () {
            Redis::shouldReceive('pubsub')
                ->andThrow(new Exception('Redis error'));
            
            $count = $this->service->getGlobalChannelSubscribers();
            
            expect($count)->toBe(0);
            Log::shouldHaveReceived('error')
                ->once()
                ->with('Failed to get global channel subscribers', [
                    'error' => 'Redis error'
                ]);
        });
    });

    describe('Test Events', function () {
        it('sends test event successfully', function () {
            $result = $this->service->sendTestEvent($this->user->id);
            
            expect($result)->toBeTrue();
            
            Redis::shouldHaveReceived('publish')
                ->once()
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return $data['event'] === 'test' && 
                           $data['user_id'] === $this->user->id &&
                           $data['message'] === 'This is a test event';
                });
        });

        it('handles test event failures', function () {
            Redis::shouldReceive('publish')
                ->andThrow(new Exception('Redis error'));
            
            $result = $this->service->sendTestEvent($this->user->id);
            
            expect($result)->toBeFalse();
            Log::shouldHaveReceived('error')
                ->once()
                ->with('Failed to send test event', [
                    'user_id' => $this->user->id,
                    'error' => 'Redis error'
                ]);
        });
    });

    describe('Event Statistics', function () {
        it('gets event statistics successfully', function () {
            Redis::shouldReceive('pubsub')
                ->with('channels', 'task_events*')
                ->andReturn(['task_events:1', 'task_events:2']);
            
            Redis::shouldReceive('pubsub')
                ->with('channels', 'user_task_events*')
                ->andReturn(['user_task_events:1', 'user_task_events:2']);
            
            Redis::shouldReceive('pubsub')
                ->with('numsub', Mockery::any())
                ->andReturn(['channel', 3]);
            
            $stats = $this->service->getEventStatistics();
            
            expect($stats['status'])->toBe('healthy');
            expect($stats)->toHaveKey('total_active_channels');
            expect($stats)->toHaveKey('total_subscribers');
            expect($stats)->toHaveKey('channel_details');
            expect($stats)->toHaveKey('global_channel_subscribers');
        });

        it('handles statistics errors gracefully', function () {
            Redis::shouldReceive('pubsub')
                ->andThrow(new Exception('Redis error'));
            
            $stats = $this->service->getEventStatistics();
            
            expect($stats['status'])->toBe('unhealthy');
            expect($stats['error'])->toBe('Redis error');
            
            Log::shouldHaveReceived('error')
                ->once()
                ->with('Failed to get event statistics', [
                    'error' => 'Redis error'
                ]);
        });
    });

    describe('Health Check', function () {
        it('returns true when Redis pub/sub is healthy', function () {
            expect($this->service->isHealthy())->toBeTrue();
            
            Redis::shouldHaveReceived('publish')
                ->once()
                ->withArgs(function ($channel, $message) {
                    return str_starts_with($channel, 'health_check_') &&
                           str_contains($message, '"test":"ok"');
                });
        });

        it('returns false when Redis pub/sub fails', function () {
            Redis::shouldReceive('publish')
                ->andThrow(new Exception('Redis error'));
            
            expect($this->service->isHealthy())->toBeFalse();
            
            Log::shouldHaveReceived('error')
                ->once()
                ->with('Redis pub/sub health check failed', [
                    'error' => 'Redis error'
                ]);
        });
    });

    describe('Event Data Preparation', function () {
        it('prepares task data correctly', function () {
            $task = Task::factory()->for($this->user)->create([
                'name' => ['en' => 'Test Task'],
                'description' => ['en' => 'Test Description'],
                'status' => 'pending',
                'priority' => 'high',
                'due_date' => now()->addDays(7)
            ]);
            
            $this->service->broadcastTaskCreated($task);
            
            Redis::shouldHaveReceived('publish')
                ->withArgs(function ($channel, $message) use ($task) {
                    $data = json_decode($message, true);
                    $taskData = $data['task'];
                    
                    return $taskData['id'] === $task->id &&
                           $taskData['name'] === $task->name &&
                           $taskData['description'] === $task->description &&
                           $taskData['status'] === $task->status &&
                           $taskData['priority'] === $task->priority &&
                           $taskData['user_id'] === $task->user_id &&
                           isset($taskData['created_at']) &&
                           isset($taskData['updated_at']);
                });
        });

        it('includes timestamp in event data', function () {
            $task = Task::factory()->for($this->user)->create();
            
            $this->service->broadcastTaskCreated($task);
            
            Redis::shouldHaveReceived('publish')
                ->withArgs(function ($channel, $message) {
                    $data = json_decode($message, true);
                    return isset($data['timestamp']) && 
                           !empty($data['timestamp']);
                });
        });
    });
});