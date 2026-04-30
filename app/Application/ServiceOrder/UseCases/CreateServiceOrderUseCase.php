<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\CreateServiceOrderDTO;
use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Application\ServiceOrderItem\UseCases\CreateServiceOrderItemUseCase;
use App\Domain\Customer\Entities\Customer;
use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Domain\Customer\ValueObjects\Document;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrder\Events\ServiceOrderCreated;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private CustomerRepositoryInterface $customerRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private ItemRepositoryInterface $itemRepository,
        private ServiceOrderItemInterface $serviceOrderItemRepository
    ) {}

    public function execute(CreateServiceOrderDTO $dto): ServiceOrder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

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



        DB::beginTransaction();


        $services = $this->resolveServices($dto->services);

        $serviceOrder = ServiceOrder::create(
            customerId: $customer->id,
            customerDocument: $customer->document,
            vehicleId: $dto->vehicleId,
            services: $services
        );


        $items = $this->resolveItems($dto->items);

        if ($dto->sendQuote) {
            $serviceOrder->sendQuoteForApproval();
        }

        $this->serviceOrderRepository->save($serviceOrder);

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
        return array_map(function (array $item) {
            $part = $this->itemRepository->findById($item['item']);

            $createServiceOrderItemDTO = new CreateServiceOrderItemDTO(
                service_order_id: '',
                item_id: $item['item'],
                quantity: (float) $item['quantity'],
                price: $part->unitPrice ?? 0.0
            );

            $useCase = $this->serviceOrderItemRepository->createServiceOrderItem($createServiceOrderItemDTO);

            return $useCase;
        }, $items);
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
}
