<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class TaskEventService
{
    /**
     * Channel prefix for task events
     */
    private const CHANNEL_PREFIX = 'task_events';

    /**
     * Channel for user-specific task events
     */
    private const USER_CHANNEL_PREFIX = 'user_task_events';

    /**
     * Channel for global task events
     */
    private const GLOBAL_CHANNEL = 'global_task_events';

    /**
     * Broadcast task creation event
     *
     * @param Task $task
     * @return void
     */
    public function broadcastTaskCreated(Task $task): void
    {
        $eventData = $this->prepareTaskEventData($task, 'created');
        
        $this->publishToUserChannel($task->user_id, $eventData);
        $this->publishToGlobalChannel($eventData);
        
        Log::info('Task created event broadcasted', [
            'task_id' => $task->id,
            'user_id' => $task->user_id
        ]);
    }

    /**
     * Broadcast task update event
     *
     * @param Task $task
     * @param array $changes
     * @return void
     */
    public function broadcastTaskUpdated(Task $task, array $changes = []): void
    {
        $eventData = $this->prepareTaskEventData($task, 'updated', [
            'changes' => $changes
        ]);
        
        $this->publishToUserChannel($task->user_id, $eventData);
        $this->publishToGlobalChannel($eventData);
        
        // If task has subtasks, notify about parent change
        if ($task->subtasks()->exists()) {
            $this->broadcastSubtaskParentUpdated($task);
        }
        
        // If task has a parent, notify about subtask change
        if ($task->parent_id) {
            $this->broadcastParentSubtaskUpdated($task);
        }
        
        Log::info('Task updated event broadcasted', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'changes' => array_keys($changes)
        ]);
    }

    /**
     * Broadcast task completion event
     *
     * @param Task $task
     * @return void
     */
    public function broadcastTaskCompleted(Task $task): void
    {
        $eventData = $this->prepareTaskEventData($task, 'completed');
        
        $this->publishToUserChannel($task->user_id, $eventData);
        $this->publishToGlobalChannel($eventData);
        
        Log::info('Task completed event broadcasted', [
            'task_id' => $task->id,
            'user_id' => $task->user_id
        ]);
    }

    /**
     * Broadcast task deletion event
     *
     * @param Task $task
     * @return void
     */
    public function broadcastTaskDeleted(Task $task): void
    {
        $eventData = $this->prepareTaskEventData($task, 'deleted');
        
        $this->publishToUserChannel($task->user_id, $eventData);
        $this->publishToGlobalChannel($eventData);
        
        Log::info('Task deleted event broadcasted', [
            'task_id' => $task->id,
            'user_id' => $task->user_id
        ]);
    }

    /**
     * Broadcast task restoration event
     *
     * @param Task $task
     * @return void
     */
    public function broadcastTaskRestored(Task $task): void
    {
        $eventData = $this->prepareTaskEventData($task, 'restored');
        
        $this->publishToUserChannel($task->user_id, $eventData);
        $this->publishToGlobalChannel($eventData);
        
        Log::info('Task restored event broadcasted', [
            'task_id' => $task->id,
            'user_id' => $task->user_id
        ]);
    }

    /**
     * Broadcast subtask parent update event
     *
     * @param Task $parentTask
     * @return void
     */
    public function broadcastSubtaskParentUpdated(Task $parentTask): void
    {
        $subtasks = $parentTask->subtasks()->get();
        
        foreach ($subtasks as $subtask) {
            $eventData = $this->prepareTaskEventData($subtask, 'parent_updated', [
                'parent_task' => $this->prepareTaskData($parentTask)
            ]);
            
            $this->publishToUserChannel($subtask->user_id, $eventData);
        }
        
        Log::info('Subtask parent updated events broadcasted', [
            'parent_task_id' => $parentTask->id,
            'subtask_count' => $subtasks->count()
        ]);
    }

    /**
     * Broadcast parent subtask update event
     *
     * @param Task $subtask
     * @return void
     */
    public function broadcastParentSubtaskUpdated(Task $subtask): void
    {
        if (!$subtask->parent_id) {
            return;
        }
        
        $parentTask = $subtask->parent;
        if (!$parentTask) {
            return;
        }
        
        $eventData = $this->prepareTaskEventData($parentTask, 'subtask_updated', [
            'updated_subtask' => $this->prepareTaskData($subtask)
        ]);
        
        $this->publishToUserChannel($parentTask->user_id, $eventData);
        
        Log::info('Parent subtask updated event broadcasted', [
            'parent_task_id' => $parentTask->id,
            'subtask_id' => $subtask->id
        ]);
    }

    /**
     * Broadcast user statistics update
     *
     * @param int $userId
     * @param array $stats
     * @return void
     */
    public function broadcastUserStatsUpdated(int $userId, array $stats): void
    {
        $eventData = [
            'event' => 'user_stats_updated',
            'user_id' => $userId,
            'stats' => $stats,
            'timestamp' => now()->toISOString()
        ];
        
        $this->publishToUserChannel($userId, $eventData);
        
        Log::info('User stats updated event broadcasted', [
            'user_id' => $userId
        ]);
    }

    /**
     * Subscribe to user-specific task events
     *
     * @param int $userId
     * @param callable $callback
     * @return void
     */
    public function subscribeToUserEvents(int $userId, callable $callback): void
    {
        $channel = $this->getUserChannelName($userId);
        
        try {
            Redis::subscribe([$channel], function ($message) use ($callback) {
                $data = json_decode($message, true);
                if ($data) {
                    $callback($data);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to user events', [
                'user_id' => $userId,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Subscribe to global task events
     *
     * @param callable $callback
     * @return void
     */
    public function subscribeToGlobalEvents(callable $callback): void
    {
        try {
            Redis::subscribe([self::GLOBAL_CHANNEL], function ($message) use ($callback) {
                $data = json_decode($message, true);
                if ($data) {
                    $callback($data);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to global events', [
                'channel' => self::GLOBAL_CHANNEL,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get active subscribers count for a user channel
     *
     * @param int $userId
     * @return int
     */
    public function getUserChannelSubscribers(int $userId): int
    {
        try {
            $channel = $this->getUserChannelName($userId);
            $subscribers = Redis::pubsub('numsub', $channel);
            
            return isset($subscribers[1]) ? (int) $subscribers[1] : 0;
        } catch (\Exception $e) {
            Log::error('Failed to get user channel subscribers', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Get active subscribers count for global channel
     *
     * @return int
     */
    public function getGlobalChannelSubscribers(): int
    {
        try {
            $subscribers = Redis::pubsub('numsub', self::GLOBAL_CHANNEL);
            
            return isset($subscribers[1]) ? (int) $subscribers[1] : 0;
        } catch (\Exception $e) {
            Log::error('Failed to get global channel subscribers', [
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Send a test event to user channel
     *
     * @param int $userId
     * @return bool
     */
    public function sendTestEvent(int $userId): bool
    {
        try {
            $eventData = [
                'event' => 'test',
                'user_id' => $userId,
                'message' => 'This is a test event',
                'timestamp' => now()->toISOString()
            ];
            
            $this->publishToUserChannel($userId, $eventData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send test event', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get real-time event statistics
     *
     * @return array
     */
    public function getEventStatistics(): array
    {
        try {
            $redis = Redis::connection();
            
            // Get all active channels
            $channels = $redis->pubsub('channels', self::CHANNEL_PREFIX . '*');
            $userChannels = $redis->pubsub('channels', self::USER_CHANNEL_PREFIX . '*');
            
            $totalSubscribers = 0;
            $channelStats = [];
            
            // Get subscriber count for each channel
            foreach (array_merge($channels, $userChannels, [self::GLOBAL_CHANNEL]) as $channel) {
                $subscribers = $redis->pubsub('numsub', $channel);
                $count = isset($subscribers[1]) ? (int) $subscribers[1] : 0;
                $totalSubscribers += $count;
                
                if ($count > 0) {
                    $channelStats[$channel] = $count;
                }
            }
            
            return [
                'total_active_channels' => count($channelStats),
                'total_subscribers' => $totalSubscribers,
                'channel_details' => $channelStats,
                'global_channel_subscribers' => $this->getGlobalChannelSubscribers(),
                'status' => 'healthy'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get event statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prepare task event data
     *
     * @param Task $task
     * @param string $event
     * @param array $additionalData
     * @return array
     */
    private function prepareTaskEventData(Task $task, string $event, array $additionalData = []): array
    {
        return array_merge([
            'event' => $event,
            'task' => $this->prepareTaskData($task),
            'timestamp' => now()->toISOString()
        ], $additionalData);
    }

    /**
     * Prepare task data for broadcasting
     *
     * @param Task $task
     * @return array
     */
    private function prepareTaskData(Task $task): array
    {
        return [
            'id' => $task->id,
            'name' => $task->name,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toISOString(),
            'parent_id' => $task->parent_id,
            'user_id' => $task->user_id,
            'created_at' => $task->created_at->toISOString(),
            'updated_at' => $task->updated_at->toISOString(),
            'deleted_at' => $task->deleted_at?->toISOString(),
        ];
    }

    /**
     * Publish event to user-specific channel
     *
     * @param int $userId
     * @param array $eventData
     * @return void
     */
    private function publishToUserChannel(int $userId, array $eventData): void
    {
        try {
            $channel = $this->getUserChannelName($userId);
            $message = json_encode($eventData);
            
            Redis::publish($channel, $message);
        } catch (\Exception $e) {
            Log::error('Failed to publish to user channel', [
                'user_id' => $userId,
                'event' => $eventData['event'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Publish event to global channel
     *
     * @param array $eventData
     * @return void
     */
    private function publishToGlobalChannel(array $eventData): void
    {
        try {
            $message = json_encode($eventData);
            Redis::publish(self::GLOBAL_CHANNEL, $message);
        } catch (\Exception $e) {
            Log::error('Failed to publish to global channel', [
                'event' => $eventData['event'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user-specific channel name
     *
     * @param int $userId
     * @return string
     */
    private function getUserChannelName(int $userId): string
    {
        return self::USER_CHANNEL_PREFIX . ":{$userId}";
    }

    /**
     * Check if Redis pub/sub is healthy
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        try {
            $testChannel = 'health_check_' . time();
            $testMessage = json_encode(['test' => 'ok', 'timestamp' => now()->toISOString()]);
            
            Redis::publish($testChannel, $testMessage);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Redis pub/sub health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}