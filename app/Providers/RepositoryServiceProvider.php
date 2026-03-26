<?php

namespace App\Providers;

use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\VehicleRepositoryEloquent;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            VehicleRepositoryInterface::class,
            VehicleRepositoryEloquent::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
