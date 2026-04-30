<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderDTO;
use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Domain\Item\ValueObjects\MovementType;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Domain\ServiceOrder\Events\ServiceOrderQuoteSent;
use App\Domain\Vehicle\ValueObjects\Plate;

class UpdateServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private ItemRepositoryInterface $itemRepository,
        private StockMovementRepositoryInterface $stockMovementRepository
    ) {}

    public function execute(UpdateServiceOrderDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \Exception('Ordem de servico nao encontrada');
        }

        $services = $dto->services !== null ? $this->resolveServices($dto->services) : null;
        $parts    = $dto->parts !== null ? $this->resolveParts($dto->parts) : null;

        $serviceOrder->updateItems($services, $parts);

        if ($dto->status !== null) {
            $serviceOrder->changeStatus($dto->status);
        }

        if ($dto->sendQuote === true) {
            $serviceOrder->sendQuoteForApproval();
        }

        if ($dto->approveQuote === true) {
            $serviceOrder->approveQuote();
            $this->withdrawStockForParts($serviceOrder);
        }

        $this->serviceOrderRepository->update($serviceOrder);

        if ($dto->sendQuote === true) {
            event(new ServiceOrderQuoteSent($serviceOrder));
        }

        return $serviceOrder;
    }

    private function resolveServices(array $services): array
    {
        return array_map(function (array $item): array {
            $service = $this->serviceRepository->findById($item['service_id']);

            if (!$service) {
                throw new \DomainException("Servico '{$item['service_id']}' nao encontrado.");
            }

            return [
                'service_id' => $service->id,
                'name'       => $service->name,
                'quantity'   => (float) $item['quantity'],
                'unit_price' => $service->price,
            ];
        }, $services);
    }

    private function resolveParts(array $parts): array
    {
        return array_map(function (array $item): array {
            $part = $this->itemRepository->findById($item['item_id']);

            if (!$part) {
                throw new \DomainException("Peca '{$item['item_id']}' nao encontrada.");
            }

            return [
                'item_id'    => $part->id,
                'name'       => $part->name,
                'quantity'   => (float) $item['quantity'],
                'unit_price' => $part->unitPrice ?? 0.0,
            ];
        }, $parts);
    }

    private function withdrawStockForParts(ServiceOrder $serviceOrder): void
    {
        foreach ($serviceOrder->parts as $part) {
            $itemId   = $part['item_id'] ?? null;
            $quantity = (float) ($part['quantity'] ?? 0);

            if (!$itemId || $quantity <= 0) {
                continue;
            }

            $item = $this->itemRepository->findById($itemId);

            if (!$item) {
                throw new \DomainException("Peca '{$itemId}' nao encontrada ao baixar estoque.");
            }

            $previous = $item->stockQuantity;
            $item->removeStock($quantity);
            $this->itemRepository->update($item);

            $movement = StockMovement::record(
                itemId: $item->id,
                movementType: new MovementType(MovementType::WITHDRAWAL),
                quantity: $quantity,
                previousQuantity: $previous,
                currentQuantity: $item->stockQuantity,
                reason: "OS {$serviceOrder->id}",
                notes: null,
                serviceOrderId: $serviceOrder->id
            );

            $this->stockMovementRepository->save($movement);
        }
    }
}
