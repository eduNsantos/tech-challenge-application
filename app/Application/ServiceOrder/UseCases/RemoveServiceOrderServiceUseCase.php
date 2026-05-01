<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\RemoveServiceOrderServiceDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;

class RemoveServiceOrderServiceUseCase
{
    public function __construct(private ServiceOrderRepositoryInterface $serviceOrderRepository) {}

    public function execute(RemoveServiceOrderServiceDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \DomainException('Ordem de servico nao encontrada.');
        }

        $remainingServices = array_values(array_filter(
            $serviceOrder->services,
            static fn (array $service): bool => ($service['service_id'] ?? null) !== $dto->serviceId
        ));

        if (count($remainingServices) === count($serviceOrder->services)) {
            throw new \DomainException('Servico nao encontrado na ordem de servico.');
        }

        $serviceOrder->updateItems($remainingServices, null);
        $this->serviceOrderRepository->update($serviceOrder);

        return $serviceOrder;
    }
}
