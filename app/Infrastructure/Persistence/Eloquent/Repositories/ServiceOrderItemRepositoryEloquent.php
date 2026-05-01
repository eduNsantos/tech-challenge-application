<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Domain\ServiceOrderItem\Entities\ServiceOrderItem as EntitiesServiceOrderItem;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderItemModel;
use Illuminate\Support\Str;

class ServiceOrderItemRepositoryEloquent implements ServiceOrderItemInterface
{
    public function createServiceOrderItem(CreateServiceOrderItemDTO $dto): EntitiesServiceOrderItem
    {
        $result =  ServiceOrderItemModel::create([
            'id' => Str::uuid()->toString(),
            'service_order_id' => $dto->service_order_id,
            'item_id' => $dto->item_id,
            'quantity' => $dto->quantity,
            'price' => $dto->price
        ]);

        return new EntitiesServiceOrderItem(
            id: $result->id,
            service_order_id: $result->service_order_id,
            item_id: $result->item_id,
            quantity: $result->quantity,
            price: $result->price
        );

    }
}
