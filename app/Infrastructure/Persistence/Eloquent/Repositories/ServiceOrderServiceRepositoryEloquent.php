<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\ServiceOrderService\DTOs\CreateServiceOrderServiceDTO;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService as EntitiesServiceOrderService;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderService;
use Illuminate\Support\Str;

class ServiceOrderServiceRepositoryEloquent implements ServiceOrderServiceInterface
{
    public function createServiceOrderService(CreateServiceOrderServiceDTO $dto): EntitiesServiceOrderService
    {
        $result = ServiceOrderService::create([
            'id' => Str::uuid()->toString(),
            'service_order_id' => $dto->service_order_id,
            'service_id' => $dto->service_id,
            'quantity' => $dto->quantity,
            'price' => $dto->price,
            'started_at' => $dto->started_at,
            'finished_at' => $dto->finished_at,
        ]);

        return $this->toEntity($result);
    }

    public function findById(string $id): ?EntitiesServiceOrderService
    {
        $model = ServiceOrderService::find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function startService(string $id, ?int $startedUserId = null): EntitiesServiceOrderService
    {
        $model = ServiceOrderService::find($id);

        if (!$model) {
            throw new \DomainException('Servico da OS nao encontrado.');
        }

        $model->started_at = now();
        if ($startedUserId !== null) {
            $model->started_user_id = $startedUserId;
        }
        $model->save();

        return $this->toEntity($model);
    }

    public function finishService(string $id, ?int $finishedUserId = null): EntitiesServiceOrderService
    {
        $model = ServiceOrderService::find($id);

        if (!$model) {
            throw new \DomainException('Servico da OS nao encontrado.');
        }

        $model->finished_at = now();
        if ($finishedUserId !== null) {
            $model->finished_user_id = $finishedUserId;
        }
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ServiceOrderService $model): EntitiesServiceOrderService
    {
        return new EntitiesServiceOrderService(
            id: $model->id,
            service_order_id: $model->service_order_id,
            service_id: $model->service_id,
            quantity: $model->quantity,
            price: $model->price,
            started_at: $model->started_at?->toDateTimeString(),
            finished_at: $model->finished_at?->toDateTimeString(),
            started_user_id: $model->started_user_id,
            finished_user_id: $model->finished_user_id
        );
    }
}
