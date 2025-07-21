<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Auth\RegisterUserDTO;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Eloquent implementation of UserRepositoryInterface
 */
class EloquentUserRepository extends BaseEloquentRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Create a new user from registration DTO
     */
    public function createFromDTO(RegisterUserDTO $dto): User
    {
        return $this->model->create($dto->toModelData());
    }

    /**
     * Find a user by email address
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Check if a user exists by email
     */
    public function existsByEmail(string $email): bool
    {
        return $this->model->where('email', $email)->exists();
    }

    /**
     * Find a user by ID
     */
    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Get user with task statistics
     */
    public function findWithTaskStats(int $id): ?User
    {
        return $this->model->with(['tasks' => function ($query) {
            $query->select('id', 'user_id', 'status', 'created_at');
        }])->find($id);
    }

    /**
     * Find users by IDs
     */
    public function findByIds(array $ids): iterable
    {
        return $this->model->whereIn('id', $ids)->get();
    }

    /**
     * Search users by name or email
     */
    public function search(string $query): iterable
    {
        return $this->model
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->get();
    }

    /**
     * Get users with pagination
     */
    public function paginate(int $perPage = 15): object
    {
        return $this->model->paginate($perPage);
    }

    /**
     * Update user notification preferences
     */
    public function updateNotificationPreferences(User $user, array $preferences): User
    {
        $user->updateNotificationPreferences($preferences);
        return $user->fresh();
    }

    /**
     * Get users who want specific notification type
     */
    public function getUsersWantingNotification(string $notificationType): iterable
    {
        return $this->model
            ->whereJsonContains('notification_preferences->email_notifications', true)
            ->whereJsonContains("notification_preferences->{$notificationType}", true)
            ->get();
    }

    /**
     * Soft delete a user
     */
    public function softDelete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Restore a soft deleted user
     */
    public function restore(User $user): bool
    {
        return $user->restore();
    }

    /**
     * Get trashed users
     */
    public function getTrashed(): iterable
    {
        return $this->model->onlyTrashed()->get();
    }
}