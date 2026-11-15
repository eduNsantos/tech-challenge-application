<?php

namespace App\Application\ServiceOrder;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Infrastructure\Messaging\ServiceOrderStatusOutbox;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessServiceOrderExecution
{
    public function __construct(private ServiceOrderStatusOutbox $outbox) {}

    public function handle(array $payload): bool
    {
        if (! in_array($payload['type'] ?? null, ['ServiceOrderExecutionStarted', 'ServiceOrderExecutionFinished'], true)
            || ($payload['version'] ?? null) !== 1
            || ! is_string($payload['event_id'] ?? null)
            || trim($payload['event_id']) === ''
            || ! is_string($payload['service_order_id'] ?? null)
            || trim($payload['service_order_id']) === ''
            || ! is_int($payload['status_sequence'] ?? null)
            || $payload['status_sequence'] < 1) {
            throw new InvalidArgumentException('Invalid service order execution event.');
        }

        $changed = null;
        try {
            DB::transaction(function () use ($payload, &$changed): void {
                DB::table('processed_messages')->insert([
                    'event_id' => $payload['event_id'],
                    'type' => $payload['type'],
                    'processed_at' => now(),
                ]);
                $order = ServiceOrderModel::query()->lockForUpdate()->find($payload['service_order_id']);
                if ($order === null) {
                    throw (new ModelNotFoundException)->setModel(ServiceOrderModel::class, [$payload['service_order_id']]);
                }

                $latest = DB::table('outbox')
                    ->where('group_id', $order->id)
                    ->whereIn('type', ['ServiceOrderCreated', 'ServiceOrderStatusChanged'])
                    ->max('id');
                if ((int) $latest !== $payload['status_sequence']
                    || $order->status !== ServiceOrder::STATUS_EM_EXECUCAO
                    || $payload['type'] === 'ServiceOrderExecutionStarted') {
                    return;
                }

                $order->update([
                    'status' => ServiceOrder::STATUS_FINALIZADA,
                    'status_started_at' => now(),
                ]);
                $this->outbox->record($order->id, ServiceOrder::STATUS_FINALIZADA, ServiceOrder::STATUS_EM_EXECUCAO);
                $changed = new ServiceOrder(
                    id: $order->id,
                    customerId: $order->customer_id,
                    vehicleId: $order->vehicle_id,
                    services: [],
                    items: [],
                    status: $order->status,
                    servicesTotal: (float) $order->services_total,
                    itemsTotal: (float) $order->parts_total,
                    totalBudget: (float) $order->total_budget,
                    quoteSentAt: $order->quote_sent_at?->toDateTimeString(),
                    quoteApprovedAt: $order->quote_approved_at?->toDateTimeString(),
                    statusStartedAt: $order->status_started_at?->toDateTimeString(),
                );
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($changed === null) {
            return false;
        }

        event(new ServiceOrderStatusChanged($changed, ServiceOrder::STATUS_EM_EXECUCAO));

        return true;
    }
}
