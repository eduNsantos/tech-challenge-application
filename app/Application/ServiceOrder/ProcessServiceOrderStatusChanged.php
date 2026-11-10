<?php

namespace App\Application\ServiceOrder;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcessServiceOrderStatusChanged
{
    private const EVENT_TYPE = 'ServiceOrderStatusChanged';

    /** @return bool false quando a mensagem foi ignorada (duplicada, antiga ou O.S. desconhecida) */
    public function handle(array $payload): bool
    {
        if (($payload['type'] ?? null) !== self::EVENT_TYPE || ($payload['version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Tipo ou versão de evento inválidos.');
        }

        foreach (['event_id', 'service_order_id', 'status', 'occurred_at'] as $field) {
            if (empty($payload[$field])) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }

        if (! in_array($payload['status'], ServiceOrder::allowedStatuses(), true)) {
            throw new InvalidArgumentException('Status inválido: '.$payload['status']);
        }

        $oldStatus = null;
        $serviceOrder = null;

        try {
            DB::transaction(function () use ($payload, &$oldStatus, &$serviceOrder): void {
                DB::table('processed_messages')->insert([
                    'event_id' => $payload['event_id'],
                    'type' => self::EVENT_TYPE,
                    'processed_at' => now(),
                ]);

                $model = ServiceOrderModel::query()->lockForUpdate()->find($payload['service_order_id']);

                if (! $model) {
                    return;
                }

                $serviceOrder = $this->toEntity($model);

                // Ignora evento mais antigo que a última mudança de status já aplicada.
                if (Carbon::parse($payload['occurred_at'])->lt(Carbon::parse($serviceOrder->statusStartedAt))
                    || $serviceOrder->status === $payload['status']) {
                    $serviceOrder = null;

                    return;
                }

                $oldStatus = $serviceOrder->status;
                $serviceOrder->changeStatus($payload['status']);

                // Atualiza só o status: repository->update recriaria os serviços e perderia os ids do MS.
                $model->update([
                    'status' => $serviceOrder->status,
                    'status_started_at' => $serviceOrder->statusStartedAt,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if (! $serviceOrder || $oldStatus === null) {
            return false;
        }

        event(new ServiceOrderStatusChanged($serviceOrder, $oldStatus));

        return true;
    }

    private function toEntity(ServiceOrderModel $model): ServiceOrder
    {
        return new ServiceOrder(
            id: $model->id,
            customerId: $model->customer_id,
            vehicleId: $model->vehicle_id,
            services: [],
            items: [],
            status: $model->status,
            servicesTotal: (float) $model->services_total,
            itemsTotal: (float) $model->parts_total,
            totalBudget: (float) $model->total_budget,
            quoteSentAt: $model->quote_sent_at?->toDateTimeString(),
            quoteApprovedAt: $model->quote_approved_at?->toDateTimeString(),
            statusStartedAt: $model->status_started_at?->toDateTimeString(),
            approvalToken: $model->approval_token,
        );
    }
}
