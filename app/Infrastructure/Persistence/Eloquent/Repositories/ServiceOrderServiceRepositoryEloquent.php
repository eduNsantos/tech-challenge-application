<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\ServiceOrderService\DTOs\CreateServiceOrderServiceDTO;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService as EntitiesServiceOrderService;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderServiceModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceOrderServiceRepositoryEloquent implements ServiceOrderServiceInterface
{
    public function createServiceOrderService(CreateServiceOrderServiceDTO $dto): EntitiesServiceOrderService
    {
        $result = ServiceOrderServiceModel::create([
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
        $model = ServiceOrderServiceModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function startService(string $id, ?int $startedUserId = null): EntitiesServiceOrderService
    {
        $model = ServiceOrderServiceModel::find($id);

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
        $model = ServiceOrderServiceModel::find($id);

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

    public function averageExecutionTimeByService(): array
    {
        return ServiceOrderServiceModel::query()
            ->join('services', 'service_order_services.service_id', '=', 'services.id')
            ->whereNotNull('service_order_services.started_at')
            ->whereNotNull('service_order_services.finished_at')
            ->groupBy('service_order_services.service_id', 'services.name')
            ->orderBy('services.name')
            ->get([
                'service_order_services.service_id',
                'services.name as service_name',
                DB::raw('COUNT(*) as executions_count'),
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, service_order_services.started_at, service_order_services.finished_at)) as average_execution_seconds'),
            ])
            ->map(static fn ($row) => [
                'service_id' => $row->service_id,
                'service_name' => $row->service_name,
                'executions_count' => (int) $row->executions_count,
                'average_execution_seconds' => (float) $row->average_execution_seconds,
                'average_execution_minutes' => round(((float) $row->average_execution_seconds) / 60, 2),
            ])
            ->values()
            ->all();
    }

    private function toEntity(ServiceOrderServiceModel $model): EntitiesServiceOrderService
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
