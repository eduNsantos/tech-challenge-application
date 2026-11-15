<?php

namespace App\Application\ServiceOrder;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Infrastructure\Messaging\ServiceOrderStatusOutbox;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderServiceModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ProcessServiceOrderExecution
{
    private const ORDER_EVENTS = ['ServiceOrderExecutionStarted', 'ServiceOrderExecutionFinished'];

    /** Evento por serviço => coluna de data e de usuário que ele preenche. */
    private const SERVICE_EVENTS = [
        'ServiceOrderServiceStarted' => ['started_at', 'started_user_id'],
        'ServiceOrderServiceFinished' => ['finished_at', 'finished_user_id'],
    ];

    public function __construct(private ServiceOrderStatusOutbox $outbox) {}

    public function handle(array $payload): bool
    {
        if (! in_array($payload['type'] ?? null, [...self::ORDER_EVENTS, ...array_keys(self::SERVICE_EVENTS)], true)
            || ($payload['version'] ?? null) !== 1
            || ! is_string($payload['event_id'] ?? null)
            || trim($payload['event_id']) === ''
            || ! is_string($payload['service_order_id'] ?? null)
            || trim($payload['service_order_id']) === ''
            || ! is_int($payload['status_sequence'] ?? null)
            || $payload['status_sequence'] < 1) {
            throw new InvalidArgumentException('Invalid service order execution event.');
        }

        if (isset(self::SERVICE_EVENTS[$payload['type']])) {
            return $this->applyServiceEvent($payload);
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

    /**
     * Início/fim de um serviço da O.S. Não depende do status_sequence: é só a cópia do que o MS executou.
     */
    private function applyServiceEvent(array $payload): bool
    {
        [$dateColumn, $userColumn] = self::SERVICE_EVENTS[$payload['type']];
        $serviceId = $payload['service_order_service_id'] ?? null;
        $at = $payload[$dateColumn] ?? null;

        if (! is_string($serviceId) || trim($serviceId) === '' || ! is_string($at)) {
            throw new InvalidArgumentException('Invalid service order execution event.');
        }

        try {
            $at = CarbonImmutable::parse($at)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid service order execution event.');
        }

        $userId = $payload['user_id'] ?? null;
        $userId = is_numeric($userId) ? (int) $userId : null;

        try {
            return DB::transaction(function () use ($payload, $serviceId, $dateColumn, $userColumn, $at, $userId): bool {
                DB::table('processed_messages')->insert([
                    'event_id' => $payload['event_id'],
                    'type' => $payload['type'],
                    'processed_at' => now(),
                ]);

                if (! ServiceOrderModel::query()->whereKey($payload['service_order_id'])->exists()) {
                    throw (new ModelNotFoundException)->setModel(ServiceOrderModel::class, [$payload['service_order_id']]);
                }

                $updated = ServiceOrderServiceModel::query()
                    ->whereKey($serviceId)
                    ->where('service_order_id', $payload['service_order_id'])
                    ->update([$dateColumn => $at, $userColumn => $userId]);

                if ($updated === 0) {
                    // Serviço removido da O.S.: reprocessar não resolve e travaria o grupo FIFO.
                    Log::warning('Service order execution event for unknown service ignored.', [
                        'event_id' => $payload['event_id'],
                        'service_order_id' => $payload['service_order_id'],
                        'service_order_service_id' => $serviceId,
                    ]);

                    return false;
                }

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
