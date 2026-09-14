<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Support\Observability\BusinessTelemetry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateServiceOrderStatusUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $repository
    ) {}

    public function execute(UpdateServiceOrderStatusDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->repository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \DomainException('Ordem de servico nao encontrada');
        }
        $oldStatus = $serviceOrder->status;
        $previousStatusStartedAt = $serviceOrder->statusStartedAt;

        $previous = Carbon::parse($previousStatusStartedAt);

        $statusDurationSeconds = $previous->diffInSeconds(now());

        Log::info('Updating service order status', [
            'service_order_id' => $serviceOrder->id ?? null,
            'old_status' => $oldStatus,
            'new_status' => $dto->status,
            'previous_status_started_at' => $previousStatusStartedAt,
            'status_started_at' => now()->toDateTimeString(),
            'status_duration_seconds' => $statusDurationSeconds,
        ]);

        $serviceOrder->changeStatus($dto->status);

        $this->repository->update($serviceOrder);
        event(new ServiceOrderStatusChanged($serviceOrder, $oldStatus));

        BusinessTelemetry::statusTransition($oldStatus, $dto->status, $serviceOrder, [
            'status_duration_seconds' => $statusDurationSeconds,
            'status_started_at' => $serviceOrder->statusStartedAt,
            'previous_status_started_at' => $previousStatusStartedAt,
        ]);

        BusinessTelemetry::serviceOrder('service_order_status_changed', $serviceOrder, [
            'old_status' => $oldStatus,
            'new_status' => $dto->status,
            'status_duration_seconds' => $statusDurationSeconds,
        ]);

        return $serviceOrder;
    }
}
