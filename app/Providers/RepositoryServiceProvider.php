<?php

namespace App\Providers;

use App\Domain\Customer\interfaces\CustomerRepositoryInterface;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\CustomerRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\NotificationRepositoryEloquent;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\CustomerRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\ServiceOrderRepositoryEloquent;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\CustomerRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\ItemRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\StockMovementRepositoryEloquent;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\CustomerRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\ServiceRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\VehicleRepositoryEloquent;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
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
            ServiceOrderRepositoryInterface::class,
            ServiceOrderRepositoryEloquent::class

        $this->app->bind(
            ServiceRepositoryInterface::class,
            ServiceRepositoryEloquent::class
        );
    }

        $this->app->bind(
            ItemRepositoryInterface::class,
            ItemRepositoryEloquent::class
        );

        $this->app->bind(
            StockMovementRepositoryInterface::class,
            StockMovementRepositoryEloquent::class
        );
    }

    public function boot(): void {}
}
