<?php

namespace App\Repositories\Contracts;

/**
 * Base repository interface defining common CRUD operations
 */
interface BaseRepositoryInterface
{
    /**
     * Find a model by its primary key
     */
    public function find(int $id): ?object;

    /**
     * Find a model by its primary key or throw an exception
     */
    public function findOrFail(int $id): object;

    /**
     * Get all models
     */
    public function all(): iterable;

    /**
     * Create a new model
     */
    public function create(array $data): object;

    /**
     * Update an existing model
     */
    public function update(object $model, array $data): object;

    /**
     * Delete a model
     */
    public function delete(object $model): bool;

    /**
     * Save a model
     */
    public function save(object $model): bool;
}