<?php

namespace App\Console\Commands;

use App\Infrastructure\Messaging\ServiceOrderStatusOutbox;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceOrderModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Publica o status atual de cada O.S. já conhecida pelo Services MS.
 *
 * O.S. importadas antes de a aplicação ser dona do status não têm sequência no MS,
 * e o MS recusa início/fim de execução até receber um ServiceOrderStatusChanged.
 * Rodar novamente é seguro: cada execução gera uma sequência maior com o mesmo status.
 */
class SyncServiceOrderStatusToServicesMs extends Command
{
    protected $signature = 'services-ms:sync-service-order-status
        {--dry-run : Count service orders without writing outbox events}
        {--chunk=100 : Number of service orders to process per batch}';

    protected $description = 'Queue the current status of service orders already imported into the services microservice';

    public function handle(ServiceOrderStatusOutbox $outbox): int
    {
        if (! config('services_ms.enabled') && ! $this->option('dry-run')) {
            $this->error('Set SERVICES_MS_ENABLED=true before running the status sync.');

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
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('outbox')
                    ->whereColumn('outbox.group_id', 'service_orders.id')
                    ->where('outbox.type', 'ServiceOrderCreated');
            })
            ->orderBy('service_orders.id')
            ->chunkById($chunkSize, function ($orders) use ($outbox, &$queued): void {
                foreach ($orders as $order) {
                    if (! $this->option('dry-run')) {
                        DB::transaction(fn () => $outbox->record($order->id, $order->status, $order->status));
                    }

                    $queued++;
                }
            }, 'service_orders.id', 'id');

        $this->info(($this->option('dry-run') ? 'Would queue ' : 'Queued ').$queued.' status snapshots.');

        return self::SUCCESS;
    }
}
