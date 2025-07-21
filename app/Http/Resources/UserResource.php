<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'preferred_language' => $this->preferred_language ?? 'en',
            'timezone' => $this->timezone ?? 'UTC',
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Only include task counts when explicitly loaded
            'task_counts' => $this->when($this->relationLoaded('tasks'), function () {
                return [
                    'total' => $this->tasks->count(),
                    'pending' => $this->tasks->where('status', 'pending')->count(),
                    'in_progress' => $this->tasks->where('status', 'in_progress')->count(),
                    'completed' => $this->tasks->where('status', 'completed')->count(),
                    'cancelled' => $this->tasks->where('status', 'cancelled')->count(),
                ];
            }),
        ];
    }
}