<?php

namespace App\Console\Commands;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Infrastructure\Messaging\ServiceOrderCreatedOutbox;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillServiceOrdersToServicesMs extends Command
{
    protected $signature = 'services-ms:backfill-service-orders
        {--dry-run : Count eligible service orders without writing outbox events}
        {--chunk=100 : Number of service orders to process per batch}';

    protected $description = 'Queue existing service orders for import into the services microservice';

    public function handle(ServiceOrderCreatedOutbox $outbox): int
    {
        if (! config('services_ms.enabled') && ! $this->option('dry-run')) {
            $this->error('Set SERVICES_MS_ENABLED=true before running the backfill.');

            return self::FAILURE;
        }

        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);

        if ($chunkSize === false) {
            $this->error('The --chunk value must be between 1 and 1000.');

            return self::FAILURE;
        }

        $queued = 0;
        ServiceOrderModel::query()
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('outbox')
                    ->whereColumn('outbox.group_id', 'service_orders.id')
                    ->where('outbox.type', 'ServiceOrderCreated');
            })
            ->orderBy('service_orders.id')
            ->chunkById($chunkSize, function ($orders) use ($outbox, &$queued): void {
                foreach ($orders as $order) {
                    $services = DB::table('service_order_services')
                        ->join('services', 'services.id', '=', 'service_order_services.service_id')
                        ->where('service_order_services.service_order_id', $order->id)
                        ->orderBy('service_order_services.id')
                        ->get([
                            'service_order_services.id',
                            'service_order_services.service_id',
                            'services.name',
                            'service_order_services.quantity',
                            'service_order_services.price as unit_price',
                        ])
                        ->map(static fn ($service): array => [
                            'id' => $service->id,
                            'service_id' => $service->service_id,
                            'name' => $service->name,
                            'quantity' => (int) $service->quantity,
                            'unit_price' => (float) $service->unit_price,
                        ])
                        ->all();

                    if ($this->option('dry-run')) {
                        $queued++;

                        continue;
                    }

                    $outbox->record($this->toEntity($order), $services);
                    $queued++;
                }
            }, 'service_orders.id');

        $this->info(($this->option('dry-run') ? 'Would queue ' : 'Queued ').$queued.' service orders.');

        return self::SUCCESS;
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
            approvalToken: null,
        );
    }
}
