<?php

namespace App\Providers;

use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\CustomerRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\ItemRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\NotificationRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\ServiceOrderRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\ServiceRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\StockMovementRepositoryEloquent;
use App\Infrastructure\Persistence\Eloquent\Repositories\VehicleRepositoryEloquent;
use Illuminate\Support\ServiceProvider;
use ServiceOrderItemRepositoryEloquent;

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
        );

        $this->app->bind(
            ServiceOrderRepositoryInterface::class,
            ServiceOrderRepositoryEloquent::class
        );

        $this->app->bind(
            ServiceRepositoryInterface::class,
            ServiceRepositoryEloquent::class
        );

        $this->app->bind(
            ItemRepositoryInterface::class,
            ItemRepositoryEloquent::class
        );

        $this->app->bind(
            StockMovementRepositoryInterface::class,
            StockMovementRepositoryEloquent::class
        );

        $this->app->bind(
            ServiceOrderItemInterface::class,
            ServiceOrderItemRepositoryEloquent::class
        );

        //Parei no momento de salvar os items no database, preciso criar o repository e a entidade de ServiceOrderItem para isso
        // ja estão criados, preciso ver o que ele está salvando no banco e adaptar para a minha entidade, depois disso é só chamar o repository no use case e salvar os itens um por um
    }

    public function boot(): void {
        // No boot logic needed for this provider
    }
}
