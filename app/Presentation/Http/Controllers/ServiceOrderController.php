<?php

namespace App\Presentation\Http\Controllers;

use App\Application\ServiceOrder\DTOs\CreateServiceOrderDTO;
use App\Application\ServiceOrder\DTOs\DeleteServiceOrderDTO;
use App\Application\ServiceOrder\DTOs\ListServiceOrderDTO;
use App\Application\ServiceOrder\DTOs\ShowServiceOrderDTO;
use App\Application\ServiceOrder\DTOs\UpdateServiceOrderDTO;
use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Application\ServiceOrder\UseCases\CreateServiceOrderUseCase;
use App\Application\ServiceOrder\UseCases\DeleteServiceOrderUseCase;
use App\Application\ServiceOrder\UseCases\ListServiceOrderUseCase;
use App\Application\ServiceOrder\UseCases\ShowServiceOrderUseCase;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderStatusUseCase;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Presentation\Http\Requests\CreateServiceOrderRequest;
use App\Presentation\Http\Requests\ListServiceOrderRequest;
use App\Presentation\Http\Requests\UpdateServiceOrderRequest;
use App\Presentation\Http\Requests\UpdateServiceOrderStatusRequest;

class ServiceOrderController
{
    public function store(CreateServiceOrderRequest $request, CreateServiceOrderUseCase $useCase)
    {
        $dto = new CreateServiceOrderDTO(
            user: $request->user(),
            vehicleId: $request->input('vehicle_id'),
            services: $request->input('services', []),
            items: $request->input('items', []),
            sendQuote: $request->boolean('send_quote', true)
        );

        $serviceOrder = $useCase->execute($dto);

        return response()->json([
            'service_order' => $this->present($serviceOrder),
            'message' => 'Ordem de servico criada com sucesso',
        ], 201);
    }

    public function list(ListServiceOrderRequest $request, ListServiceOrderUseCase $useCase)
    {
        $dto = new ListServiceOrderDTO(
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('perPage', 10)
        );

        $serviceOrders = $useCase->execute($dto);

        return response()->json($serviceOrders);
    }

    public function show(string $id, ShowServiceOrderUseCase $useCase)
    {
        $dto = new ShowServiceOrderDTO(id: $id);
        $serviceOrder = $useCase->execute($dto);

        return response()->json([
            'service_order' => $this->present($serviceOrder),
        ]);
    }

    public function update(
        UpdateServiceOrderRequest $request,
        UpdateServiceOrderUseCase $useCase
    ) {
        $dto = new UpdateServiceOrderDTO(
            id: $request->route('id'),
            services: $request->input('services'),
            items: $request->input('items', $request->input('parts')),
            vehicleId: $request->input('vehicle_id'),
            status: $request->input('status'),
            sendQuote: $request->has('send_quote') ? $request->boolean('send_quote') : null,
            approveQuote: $request->has('approve_quote') ? $request->boolean('approve_quote') : null,
        );

        $serviceOrder = $useCase->execute($dto);

        return response()->json([
            'service_order' => $this->present($serviceOrder),
            'message' => 'Ordem de servico atualizada com sucesso',
        ]);
    }

    public function updateStatus(
        UpdateServiceOrderStatusRequest $request,
        UpdateServiceOrderStatusUseCase $useCase
    ) {
        $dto = new UpdateServiceOrderStatusDTO(
            id: $request->route('id'),
            status: $request->input('status')
        );

        $serviceOrder = $useCase->execute($dto);

        return response()->json([
            'service_order' => $this->present($serviceOrder),
            'message' => 'Status da ordem de servico atualizado com sucesso',
        ]);
    }

    public function destroy(string $id, DeleteServiceOrderUseCase $useCase)
    {
        $dto = new DeleteServiceOrderDTO(id: $id);
        $useCase->execute($dto);

        return response()->noContent();
    }

    private function present(ServiceOrder $serviceOrder): array
    {
        return [
            'id' => $serviceOrder->id,
            'customer_id' => $serviceOrder->customerId,
            'customer_document' => $serviceOrder->customerDocument,
            'vehicle_id' => $serviceOrder->vehicleId,
            'services' => $serviceOrder->services,
            'items' => $serviceOrder->items,
            'status' => $serviceOrder->status,
            'services_total' => $serviceOrder->servicesTotal,
            'items_total' => $serviceOrder->itemsTotal,
            'total_budget' => $serviceOrder->totalBudget,
            'quote_sent_at' => $serviceOrder->quoteSentAt,
            'quote_approved_at' => $serviceOrder->quoteApprovedAt,
        ];
    }
}
