<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderServiceModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $services = ServiceOrderServiceModel::query()
            ->where('service_order_id', $id)
            ->get(['service_id', 'quantity', 'price'])
            ->map(static fn ($service) => [
                'service_id' => $service->service_id,
                'quantity' => (float) $service->quantity,
                'unit_price' => (float) $service->price,
            ])
            ->values()
            ->all();

        $items = ServiceOrderItemModel::query()
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
        return ServiceOrderModel::with(['customer', 'vehicle', 'services', 'services.service', 'items', 'items.item'])->get()->toArray();
    }

    public function paginate(int $page, int $perPage): array
    {
        return ServiceOrderModel::with(['customer', 'vehicle', 'services', 'services.service', 'items', 'items.item'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function update(ServiceOrder $serviceOrder): void
    {
        DB::transaction(function () use ($serviceOrder): void {
            ServiceOrderModel::where('id', $serviceOrder->id)->update([
                'status' => $serviceOrder->status,
                'services_total' => $serviceOrder->servicesTotal,
                'parts_total' => $serviceOrder->itemsTotal,
                'total_budget' => $serviceOrder->totalBudget,
                'quote_sent_at' => $serviceOrder->quoteSentAt,
                'quote_approved_at' => $serviceOrder->quoteApprovedAt,
                'updated_user_id' => Auth::id(),
            ]);

            $this->syncServices($serviceOrder);
            $this->syncItems($serviceOrder);
        });
    }

    private function syncServices(ServiceOrder $serviceOrder): void
    {
        ServiceOrderServiceModel::query()
            ->where('service_order_id', $serviceOrder->id)
            ->delete();

        foreach ($serviceOrder->services as $service) {
            ServiceOrderServiceModel::query()->create([
                'id' => Str::uuid()->toString(),
                'service_order_id' => $serviceOrder->id,
                'service_id' => $service['service_id'],
                'quantity' => (int) ($service['quantity'] ?? 0),
                'price' => (float) ($service['unit_price'] ?? 0),
            ]);
        }
    }

    private function syncItems(ServiceOrder $serviceOrder): void
    {
        ServiceOrderItemModel::query()
            ->where('service_order_id', $serviceOrder->id)
            ->delete();

        foreach ($serviceOrder->items as $item) {
            ServiceOrderItemModel::query()->create([
                'id' => Str::uuid()->toString(),
                'service_order_id' => $serviceOrder->id,
                'item_id' => $item['item_id'],
                'quantity' => (int) ($item['quantity'] ?? 0),
                'price' => (float) ($item['unit_price'] ?? 0),
            ]);
        }
    }

    public function delete(string $id): void
    {
        ServiceOrderModel::where('id', $id)->delete();
    }
}
