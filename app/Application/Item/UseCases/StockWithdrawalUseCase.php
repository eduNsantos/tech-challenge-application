<?php

namespace App\Application\Item\UseCases;

use App\Application\Item\DTOs\StockWithdrawalDTO;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\MovementType;

class StockWithdrawalUseCase
{
    public function __construct(
        private ItemRepositoryInterface $itemRepository,
        private StockMovementRepositoryInterface $movementRepository
    ) {}

    public function execute(StockWithdrawalDTO $dto): StockMovement
    {
        $item = $this->itemRepository->findById($dto->itemId);

        if (!$item) {
            throw new \DomainException('Item not found.');
        }

        $previousQuantity = $item->stockQuantity;

        $item->removeStock($dto->quantity);

        $this->itemRepository->update($item);

        // TODO: implementar a associacao de ordem de servico com a movimentacao de saida.
        // O StockMovement deve receber um service_order_id (nullable) para rastreabilidade,
        // faturamento e auditoria. Requer: novo dominio ServiceOrder, campo service_order_id
        // na tabela stock_movements (nullable FK) e atualizacao do DTO/entidade/repositorio.
        $movement = StockMovement::record(
            itemId: $item->id,
            movementType: new MovementType(MovementType::WITHDRAWAL),
            quantity: $dto->quantity,
            previousQuantity: $previousQuantity,
            currentQuantity: $item->stockQuantity,
            reason: $dto->reason,
            notes: $dto->notes
        );

        $this->movementRepository->save($movement);

        return $movement;
    }
}
