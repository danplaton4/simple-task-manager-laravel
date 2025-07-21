<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;

/**
 * Service provider for repository interface bindings
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\UserRepositoryInterface::class,
            function ($app) {
                return new \App\Repositories\Eloquent\EloquentUserRepository(new \App\Models\User());
            }
        );
        $this->app->bind(
            \App\Repositories\Contracts\TaskRepositoryInterface::class,
            function ($app) {
                return new \App\Repositories\Eloquent\EloquentTaskRepository(new \App\Models\Task());
            }
        );
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        //
    }
}