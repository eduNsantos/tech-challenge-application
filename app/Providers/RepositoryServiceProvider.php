<?php

namespace App\Providers;

use App\Domain\Customer\interfaces\CustomerRepositoryInterface;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\CustomerRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\NotificationRepositoryEloquent;
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
        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepositoryEloquent::class
        );
        $this->app->bind(
            NotificationRepositoryInterface::class,
            NotificationRepositoryEloquent::class
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
