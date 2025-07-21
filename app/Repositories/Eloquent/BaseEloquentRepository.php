<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Base Eloquent repository implementation
 */
abstract class BaseEloquentRepository implements BaseRepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Find a model by its primary key
     */
    public function find(int $id): ?object
    {
        return $this->model->find($id);
    }

    /**
     * Find a model by its primary key or throw an exception
     */
    public function findOrFail(int $id): object
    {
        $model = $this->model->find($id);
        
        if (!$model) {
            throw new ModelNotFoundException("Model not found with ID: {$id}");
        }
        
        return $model;
    }

    /**
     * Get all models
     */
    public function all(): iterable
    {
        return $this->model->all();
    }

    /**
     * Create a new model
     */
    public function create(array $data): object
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing model
     */
    public function update(object $model, array $data): object
    {
        $model->update($data);
        return $model->fresh();
    }

    /**
     * Delete a model
     */
    public function delete(object $model): bool
    {
        return $model->delete();
    }

    /**
     * Save a model
     */
    public function save(object $model): bool
    {
        return $model->save();
    }

    /**
     * Get a fresh model instance
     */
    protected function getModel(): Model
    {
        return $this->model->newInstance();
    }
}