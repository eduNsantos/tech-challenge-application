<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Support\Observability\BusinessTelemetry;
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

        $serviceOrder->changeStatus($dto->status);

        $statusDurationSeconds = $previousStatusStartedAt
            ? max(0, (int) now()->diffInSeconds(new \DateTimeImmutable($previousStatusStartedAt)))
            : null;

        $this->repository->update($serviceOrder);
        event(new ServiceOrderStatusChanged($serviceOrder, $oldStatus));

        BusinessTelemetry::statusTransition($oldStatus, $dto->status, $serviceOrder, [
            'status_duration_seconds' => $statusDurationSeconds,
            'status_started_at' => $serviceOrder->statusStartedAt,
        ]);

        BusinessTelemetry::serviceOrder('service_order_status_changed', $serviceOrder, [
            'old_status' => $oldStatus,
            'new_status' => $dto->status,
            'status_duration_seconds' => $statusDurationSeconds,
        ]);

        return $serviceOrder;
    }
}
