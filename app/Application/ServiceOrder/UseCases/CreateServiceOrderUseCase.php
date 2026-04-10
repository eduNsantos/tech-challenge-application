<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\CreateServiceOrderDTO;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Customer\interfaces\CustomerRepositoryInterface;
use App\Domain\Customer\ValueObjects\Document;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\Vehicle\Entities\Vehicle;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;
use App\Domain\Vehicle\ValueObjects\Plate;

class CreateServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private CustomerRepositoryInterface $customerRepository,
        private VehicleRepositoryInterface $vehicleRepository
    ) {}

    public function execute(CreateServiceOrderDTO $dto): ServiceOrder
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user === null) {
            throw new \Exception('Usuario autenticado nao encontrado');
        }

        if (empty($user->document)) {
            throw new \Exception('Usuario autenticado sem documento vinculado');
        }

        $document = new Document($user->document);
        $customer = $this->customerRepository->findByDocument($document->getValue());

        if (!$customer) {
            $customer = Customer::create(
                name: (string) $user->name,
                email: (string) $user->email,
                phone: 'Nao informado',
                document: $document->getValue()
            );

            $this->customerRepository->save($customer);
        }

        $plate = new Plate($dto->vehiclePlate);
        $vehicle = $this->vehicleRepository->findByPlate($plate->getValue());

        if (!$vehicle) {
            $vehicle = Vehicle::create(
                $dto->vehicleBrand,
                $dto->vehicleModel,
                $dto->vehicleYear,
                $plate
            );

            $this->vehicleRepository->save($vehicle);
        }

        $serviceOrder = ServiceOrder::create(
            customerId: $customer->id,
            customerDocument: $customer->document,
            vehicleId: $vehicle->id,
            services: $dto->services,
            parts: $dto->parts
        );

        if ($dto->sendQuote) {
            $serviceOrder->sendQuoteForApproval();
        }

        $this->serviceOrderRepository->save($serviceOrder);

        return $serviceOrder;
    }
}
