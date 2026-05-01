<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderItem;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderService;
use Illuminate\Support\Facades\Auth;

class ServiceOrderRepositoryEloquent implements ServiceOrderRepositoryInterface
{
    public function save(ServiceOrder $serviceOrder): void
    {
        ServiceOrderModel::create([
            'id' => $serviceOrder->id,
            'customer_id' => $serviceOrder->customerId,
            'vehicle_id' => $serviceOrder->vehicleId,
            'customer_document' => $serviceOrder->customerDocument,
            'status' => $serviceOrder->status,
            'services_total' => $serviceOrder->servicesTotal,
            'parts_total' => $serviceOrder->itemsTotal,
            'total_budget' => $serviceOrder->totalBudget,
            'quote_sent_at' => $serviceOrder->quoteSentAt,
            'quote_approved_at' => $serviceOrder->quoteApprovedAt,
            'created_user_id' => Auth::id(),
            'updated_user_id' => Auth::id(),
        ]);
    }

    public function findById(string $id): ?ServiceOrder
    {
        $model = ServiceOrderModel::find($id);

        if (!$model) {
            return null;
        }

        $services = ServiceOrderService::query()
            ->where('service_order_id', $id)
            ->get(['service_id', 'quantity', 'price'])
            ->map(static fn ($service) => [
                'service_id' => $service->service_id,
                'quantity' => (float) $service->quantity,
                'unit_price' => (float) $service->price,
            ])
            ->values()
            ->all();

        $items = ServiceOrderItem::query()
            ->where('service_order_id', $id)
            ->get(['item_id', 'quantity', 'price'])
            ->map(static fn ($item) => [
                'item_id' => $item->item_id,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->price,
            ])
            ->values()
            ->all();

        return new ServiceOrder(
            id: $model->id,
            customerId: $model->customer_id,
            vehicleId: $model->vehicle_id,
            customerDocument: $model->customer_document,
            services: $services,
            items: $items,
            status: $model->status,
            servicesTotal: (float) $model->services_total,
            itemsTotal: (float) $model->parts_total,
            totalBudget: (float) $model->total_budget,
            quoteSentAt: $model->quote_sent_at?->toDateTimeString(),
            quoteApprovedAt: $model->quote_approved_at?->toDateTimeString()
        );
    }

    public function findAll(): array
    {
        return ServiceOrderModel::with(['customer', 'vehicle'])->get()->toArray();
    }

    public function paginate(int $page, int $perPage): array
    {
        return ServiceOrderModel::with(['customer', 'vehicle'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function update(ServiceOrder $serviceOrder): void
    {
        ServiceOrderModel::where('id', $serviceOrder->id)->update([
            'status' => $serviceOrder->status,
            'services_total' => $serviceOrder->servicesTotal,
            'parts_total' => $serviceOrder->itemsTotal,
            'total_budget' => $serviceOrder->totalBudget,
            'quote_sent_at' => $serviceOrder->quoteSentAt,
            'quote_approved_at' => $serviceOrder->quoteApprovedAt,
            'updated_user_id' => Auth::id(),
        ]);
    }

    public function delete(string $id): void
    {
        ServiceOrderModel::where('id', $id)->delete();
    }
}
