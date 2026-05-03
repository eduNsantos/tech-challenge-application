<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\ValueObjects\MovementType;

class ApproveServiceOrderByTokenUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private ItemRepositoryInterface $itemRepository,
        private StockMovementRepositoryInterface $stockMovementRepository
    ) {}

    public function execute(string $token): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findByApprovalToken($token);

        if (!$serviceOrder) {
            throw new \DomainException('Token de aprovação inválido ou expirado.');
        }

        if ($serviceOrder->status !== ServiceOrder::STATUS_AGUARDANDO_APROVACAO) {
            throw new \DomainException('Esta ordem de serviço não está aguardando aprovação.');
        }

        $serviceOrder->approveQuote();

        foreach ($serviceOrder->items as $item) {
            $part = $this->itemRepository->findById($item['item_id']);

            if (!$part) {
                continue;
            }

            $previous = $part->stockQuantity;
            $quantity = (int) $item['quantity'];
            $part->removeStock($quantity);
            $this->itemRepository->update($part);

            $movement = StockMovement::record(
                itemId: $part->id,
                movementType: new MovementType(MovementType::WITHDRAWAL),
                quantity: $quantity,
                previousQuantity: $previous,
                currentQuantity: $part->stockQuantity,
                reason: "Aprovação de orçamento OS #{$serviceOrder->id}",
                notes: null,
                serviceOrderId: $serviceOrder->id
            );

            $this->stockMovementRepository->save($movement);
        }

        $this->serviceOrderRepository->update($serviceOrder);

        return $serviceOrder;
    }
}
