<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\CreateServiceOrderDTO;
use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Application\ServiceOrderService\DTOs\CreateServiceOrderServiceDTO;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Domain\Customer\ValueObjects\Document;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrder\Events\ServiceOrderCreated;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;
use Illuminate\Support\Facades\DB;

class CreateServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private CustomerRepositoryInterface $customerRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private ItemRepositoryInterface $itemRepository,
        private ServiceOrderItemInterface $serviceOrderItemRepository,
        private ServiceOrderServiceInterface $serviceOrderServiceRepository
    ) {}

    public function execute(CreateServiceOrderDTO $dto): ServiceOrder
    {
        /** @var \App\Models\User|null $user */
        $user = $dto->user;

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

        $services = $this->resolveServices($dto->services);
        $items = $this->resolveItems($dto->items);

        $serviceOrder = ServiceOrder::create(
            customerId: $customer->id,
            customerDocument: $customer->document,
            vehicleId: $dto->vehicleId,
            services: $services,
            items: $items
        );

        if ($dto->sendQuote) {
            $serviceOrder->sendQuoteForApproval();
        }

        DB::transaction(function () use ($serviceOrder, $items, $services): void {
            $this->serviceOrderRepository->save($serviceOrder);

            foreach ($services as $service) {
                $this->serviceOrderServiceRepository->createServiceOrderService(
                    new CreateServiceOrderServiceDTO(
                        $serviceOrder->id,
                        (string) $service['service_id'],
                        (int) $service['quantity'],
                        (float) $service['unit_price'],
                        null,
                        null
                    )
                );
            }

            foreach ($items as $item) {
                $this->serviceOrderItemRepository->createServiceOrderItem(
                    new CreateServiceOrderItemDTO(
                        service_order_id: $serviceOrder->id,
                        item_id: (string) $item['item_id'],
                        quantity: (int) $item['quantity'],
                        price: (float) $item['unit_price']
                    )
                );
            }
        });

        event(new ServiceOrderCreated($serviceOrder));

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

    private function resolveItems(array $items): array
    {
        return array_map(function (array $item): array {
            $itemId = (string) ($item['item_id'] ?? $item['item'] ?? '');

            if ($itemId === '') {
                throw new \DomainException('Item da OS sem identificador informado.');
            }

            $part = $this->itemRepository->findById($itemId);

            if (!$part) {
                throw new \DomainException("Peca '{$itemId}' nao encontrada.");
            }

            return [
                'item_id' => $part->id,
                'name' => $part->name,
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) ($part->unitPrice ?? 0.0),
            ];
        }, $items);
    }
}
