<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\DTOs\Auth\RegisterUserDTO;

/**
 * User repository interface for data access operations
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Create a new user from registration DTO
     */
    public function createFromDTO(RegisterUserDTO $dto): User;

    /**
     * Find a user by email address
     */
    public function findByEmail(string $email): ?User;

    /**
     * Check if a user exists by email
     */
    public function existsByEmail(string $email): bool;

    /**
     * Find a user by ID
     */
    public function findById(int $id): ?User;

    /**
     * Get user with task statistics
     */
    public function findWithTaskStats(int $id): ?User;
}