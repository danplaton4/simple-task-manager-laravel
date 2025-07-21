<?php

namespace App\Services;

use App\DTOs\Task\TaskDTO; // Assuming a general TaskDTO exists or will be created
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TaskEventService
{
    private const CHANNEL_PREFIX = 'task_events';

    public function broadcastTaskCreated(TaskDTO $taskDto): void
    {
        $this->publish('task.created', $taskDto->toArray());
    }

    public function broadcastTaskUpdated(TaskDTO $taskDto, array $changes = []): void
    {
        $payload = array_merge($taskDto->toArray(), ['changes' => $changes]);
        $this->publish('task.updated', $payload);
    }

    public function broadcastTaskDeleted(int $taskId, int $userId): void
    {
        $this->publish('task.deleted', ['id' => $taskId, 'user_id' => $userId]);
    }

    private function publish(string $event, array $data): void
    {
        $payload = [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];
        
        try {
            Redis::publish(self::CHANNEL_PREFIX, json_encode($payload));
            if (isset($data['user_id'])) {
                $userChannel = self::CHANNEL_PREFIX . '.user.' . $data['user_id'];
                Redis::publish($userChannel, json_encode($payload));
            }
        } catch (\Exception $e) {
            Log::error("Failed to publish event '{$event}'", [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}