<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Domain\Vehicle\ValueObjects\Plate;

class UpdateServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private VehicleRepositoryInterface $vehicleRepository
    ) {}

    public function execute(UpdateServiceOrderDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \Exception('Ordem de servico nao encontrada');
        }

        $serviceOrder->updateItems($dto->services, $dto->parts);

        if ($dto->status !== null) {
            $serviceOrder->changeStatus($dto->status);
        }

        if ($dto->sendQuote === true) {
            $serviceOrder->sendQuoteForApproval();
        }

        if ($dto->approveQuote === true) {
            $serviceOrder->approveQuote();
        }

        if (
            $dto->vehicleBrand !== null ||
            $dto->vehicleModel !== null ||
            $dto->vehicleYear !== null ||
            $dto->vehiclePlate !== null
        ) {
            $vehicle = $this->vehicleRepository->findById($serviceOrder->vehicleId);

            if (!$vehicle) {
                throw new \Exception('Veiculo da ordem de servico nao encontrado');
            }

            $plate = $dto->vehiclePlate !== null
                ? new Plate($dto->vehiclePlate)
                : null;

            if ($plate !== null) {
                $existing = $this->vehicleRepository->findByPlate($plate->getValue());

                if ($existing !== null && $existing->id !== $vehicle->id) {
                    throw new \Exception('Placa ja cadastrada para outro veiculo');
                }
            }

            $vehicle->updateData(
                $dto->vehicleBrand,
                $dto->vehicleModel,
                $dto->vehicleYear,
                $plate
            );

            $this->vehicleRepository->update($vehicle);
        }

        $this->serviceOrderRepository->update($serviceOrder);

        return $serviceOrder;
    }
}
