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
        // Repository interface bindings will be added as implementations are created
        // This provider is set up for future use
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        //
    }
}