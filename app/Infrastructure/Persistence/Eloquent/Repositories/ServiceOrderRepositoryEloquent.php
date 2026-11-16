<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Infrastructure\Messaging\ServiceOrderStatusOutbox;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderServiceModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceOrderRepositoryEloquent implements ServiceOrderRepositoryInterface
{
    public function __construct(private ServiceOrderStatusOutbox $statusOutbox) {}

    public function save(ServiceOrder $serviceOrder): void
    {
        DB::transaction(function () use ($serviceOrder): void {
            $this->createServiceOrder($serviceOrder);
            $this->syncServices($serviceOrder);
        });
    }

    private function createServiceOrder(ServiceOrder $serviceOrder): void
    {
        ServiceOrderModel::create([
            'id' => $serviceOrder->id,
            'customer_id' => $serviceOrder->customerId,
            'vehicle_id' => $serviceOrder->vehicleId,
            'status' => $serviceOrder->status,
            'status_started_at' => $serviceOrder->statusStartedAt,
            'services_total' => $serviceOrder->servicesTotal,
            'parts_total' => $serviceOrder->itemsTotal,
            'total_budget' => $serviceOrder->totalBudget,
            'quote_sent_at' => $serviceOrder->quoteSentAt,
            'quote_approved_at' => $serviceOrder->quoteApprovedAt,
            'approval_token' => $serviceOrder->approvalToken,
            'payment_order_id' => $serviceOrder->paymentOrderId,
            'payment_callback_token' => $serviceOrder->paymentCallbackToken,
            'payment_link' => $serviceOrder->paymentLink,
            'created_user_id' => Auth::id(),
            'updated_user_id' => Auth::id(),
        ]);
    }

    public function findById(string $id): ?ServiceOrder
    {
        $model = ServiceOrderModel::find($id);

        if (! $model) {
            return null;
        }

        $services = ServiceOrderServiceModel::query()
            ->join('services', 'service_order_services.service_id', '=', 'services.id')
            ->where('service_order_services.service_order_id', $id)
            ->get([
                'service_order_services.id',
                'service_order_services.service_id',
                'service_order_services.quantity',
                'service_order_services.price as price',
                'service_order_services.started_at',
                'service_order_services.finished_at',
                'services.name as name',
            ])
            ->map(static fn ($service) => [
                'id' => $service->id,
                'service_id' => $service->service_id,
                'quantity' => (float) $service->quantity,
                'unit_price' => (float) $service->price,
                'started_at' => $service->started_at?->toDateTimeString(),
                'finished_at' => $service->finished_at?->toDateTimeString(),
                'service' => [
                    'id' => $service->service_id,
                    'name' => $service->name,
                ],
            ])
            ->values()
            ->all();

        $items = ServiceOrderItemModel::query()
            ->join('items', 'service_order_items.item_id', '=', 'items.id')
            ->where('service_order_items.service_order_id', $id)
            ->get([
                'service_order_items.item_id',
                'service_order_items.quantity',
                'service_order_items.price as price',
                'items.name as name',
                'items.description as description',
            ])
            ->map(static fn ($item) => [
                'item_id' => $item->item_id,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->price,
                'item' => [
                    'id' => $item->item_id,
                    'name' => $item->name,
                    'description' => $item->description,
                ],
            ])
            ->values()
            ->all();

        return new ServiceOrder(
            id: $model->id,
            customerId: $model->customer_id,
            vehicleId: $model->vehicle_id,
            services: $services,
            items: $items,
            status: $model->status,
            servicesTotal: (float) $model->services_total,
            itemsTotal: (float) $model->parts_total,
            totalBudget: (float) $model->total_budget,
            quoteSentAt: $model->quote_sent_at?->toDateTimeString(),
            quoteApprovedAt: $model->quote_approved_at?->toDateTimeString(),
            statusStartedAt: $model->status_started_at?->toDateTimeString(),
            approvalToken: $model->approval_token,
            paymentOrderId: $model->payment_order_id,
            paymentCallbackToken: $model->payment_callback_token,
            paymentLink: $model->payment_link
        );
    }

    public function findAll(): array
    {
        return ServiceOrderModel::with(['customer', 'vehicle', 'services', 'services.service', 'items', 'items.item'])->get()->toArray();
    }

    public function paginate(int $page, int $perPage): array
    {
        return ServiceOrderModel::with(['customer', 'vehicle', 'services', 'services.service', 'items', 'items.item'])
            ->whereNotIn('status', ['finalizada', 'entregue'])
            ->orderByRaw("
                CASE status
                    WHEN 'em_execucao'           THEN 1
                    WHEN 'aguardando_aprovacao'  THEN 2
                    WHEN 'em_diagnostico'        THEN 3
                    WHEN 'recebida'              THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('created_at', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();
    }

    public function update(ServiceOrder $serviceOrder): void
    {
        DB::transaction(function () use ($serviceOrder): void {
            $previousStatus = ServiceOrderModel::query()
                ->whereKey($serviceOrder->id)
                ->lockForUpdate()
                ->value('status');

            $actorId = Auth::id()
                ?? ServiceOrderModel::query()->where('id', $serviceOrder->id)->value('updated_user_id')
                ?? ServiceOrderModel::query()->where('id', $serviceOrder->id)->value('created_user_id')
                ?? DB::table('users')->value('id');

            ServiceOrderModel::where('id', $serviceOrder->id)->update([
                'customer_id' => $serviceOrder->customerId,
                'vehicle_id' => $serviceOrder->vehicleId,
                'status' => $serviceOrder->status,
                'status_started_at' => $serviceOrder->statusStartedAt,
                'services_total' => $serviceOrder->servicesTotal,
                'parts_total' => $serviceOrder->itemsTotal,
                'total_budget' => $serviceOrder->totalBudget,
                'quote_sent_at' => $serviceOrder->quoteSentAt,
                'quote_approved_at' => $serviceOrder->quoteApprovedAt,
                'approval_token' => $serviceOrder->approvalToken,
                'payment_order_id' => $serviceOrder->paymentOrderId,
                'payment_callback_token' => $serviceOrder->paymentCallbackToken,
                'payment_link' => $serviceOrder->paymentLink,
                'updated_user_id' => $actorId,
            ]);

            $this->syncServices($serviceOrder);
            $this->syncItems($serviceOrder);

            if ($previousStatus !== null && $previousStatus !== $serviceOrder->status) {
                $this->statusOutbox->record($serviceOrder->id, $serviceOrder->status, $previousStatus);
            }
        });
    }

    private function syncServices(ServiceOrder $serviceOrder): void
    {
        // As linhas são recriadas; início/fim vêm do Services MS e não podem se perder num update da O.S.
        $execution = ServiceOrderServiceModel::query()
            ->where('service_order_id', $serviceOrder->id)
            ->get(['id', 'started_at', 'started_user_id', 'finished_at', 'finished_user_id'])
            ->keyBy('id');

        ServiceOrderServiceModel::query()
            ->where('service_order_id', $serviceOrder->id)
            ->delete();

        foreach ($serviceOrder->services as $service) {
            $id = $service['id'] ?? Str::uuid()->toString();
            $previous = $execution->get($id);

            ServiceOrderServiceModel::query()->create([
                'id' => $id,
                'service_order_id' => $serviceOrder->id,
                'service_id' => $service['service_id'],
                'quantity' => (int) ($service['quantity'] ?? 0),
                'price' => (float) ($service['unit_price'] ?? 0),
                'started_at' => $previous?->started_at,
                'started_user_id' => $previous?->started_user_id,
                'finished_at' => $previous?->finished_at,
                'finished_user_id' => $previous?->finished_user_id,
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

    public function findByApprovalToken(string $token): ?ServiceOrder
    {
        $model = ServiceOrderModel::where('approval_token', $token)->first();

        if (! $model) {
            return null;
        }

        return $this->findById($model->id);
    }

    public function findByApprovalTokenForUpdate(string $token): ?ServiceOrder
    {
        $model = ServiceOrderModel::query()
            ->where('approval_token', $token)
            ->lockForUpdate()
            ->first();

        if (! $model) {
            return null;
        }

        return $this->findById($model->id);
    }

    public function findByPaymentOrderId(string $paymentOrderId): ?ServiceOrder
    {
        $id = ServiceOrderModel::where('payment_order_id', $paymentOrderId)->value('id');

        return $id ? $this->findById($id) : null;
    }

    public function findByPaymentCallbackToken(string $token): ?ServiceOrder
    {
        $id = ServiceOrderModel::where('payment_callback_token', $token)->value('id');

        return $id ? $this->findById($id) : null;
    }
}
