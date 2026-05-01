<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\RemoveServiceOrderItemDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;

class RemoveServiceOrderItemUseCase
{
    public function __construct(private ServiceOrderRepositoryInterface $serviceOrderRepository) {}

    public function execute(RemoveServiceOrderItemDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \DomainException('Ordem de servico nao encontrada.');
        }

        $remainingItems = array_values(array_filter(
            $serviceOrder->items,
            static fn (array $item): bool => ($item['item_id'] ?? null) !== $dto->itemId
        ));

        if (count($remainingItems) === count($serviceOrder->items)) {
            throw new \DomainException('Item nao encontrado na ordem de servico.');
        }

        $serviceOrder->updateItems(null, $remainingItems);
        $this->serviceOrderRepository->update($serviceOrder);

        return $serviceOrder;
    }
}
