<?php

namespace App\Infrastructure\Messaging;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Publica os eventos pendentes da outbox nas filas FIFO do Services MS.
 */
class OutboxPublisher
{
    private const LOCK_NAME = 'postech-outbox-publisher';

    public function __construct(private readonly SqsClient $client) {}

    /**
     * @return int|null eventos publicados, ou null se outro publisher já estiver rodando.
     */
    public function publishPending(int $limit = 50): ?int
    {
        if (! config('services_ms.sqs.service_orders_created_url')) {
            throw new RuntimeException('SQS_SERVICE_ORDERS_CREATED_URL is not configured.');
        }

        // Um único publisher por vez preserva a ordem por O.S., mesmo com
        // invocações sobrepostas do agendamento.
        if (! $this->acquireLock()) {
            return null;
        }

        try {
            return $this->publish($limit);
        } finally {
            $this->releaseLock();
        }
    }

    private function publish(int $limit): int
    {
        $events = DB::table('outbox')->whereNull('published_at')->orderBy('id')->limit($limit)->get();
        $published = 0;
        $blockedGroups = [];

        foreach ($events as $event) {
            // Preserva a ordem por O.S.: se um evento falhar, os seguintes do mesmo grupo esperam.
            if (isset($blockedGroups[$event->group_id])) {
                continue;
            }

            $url = $this->destination($event->type);

            if (! $url) {
                report(new RuntimeException("No SQS destination configured for outbox event type {$event->type}."));
                $blockedGroups[$event->group_id] = true;

                continue;
            }

            try {
                $this->client->sendMessage([
                    'QueueUrl' => $url,
                    'MessageBody' => $event->payload,
                    'MessageGroupId' => $event->group_id,
                    'MessageDeduplicationId' => $event->event_id,
                ]);
            } catch (Throwable $e) {
                report($e);
                $blockedGroups[$event->group_id] = true;

                continue;
            }

            DB::table('outbox')->where('id', $event->id)->update(['published_at' => now()]);
            $published++;
        }

        return $published;
    }

    private function destination(string $type): ?string
    {
        return match ($type) {
            'ServiceOrderCreated', 'ServiceOrderApprovalChanged' => config('services_ms.sqs.service_orders_created_url'),
            'ServiceOrderStatusChanged' => config('services_ms.sqs.service_order_status_changed_url'),
            default => null,
        };
    }

    private function acquireLock(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return true;
        }

        return (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_NAME])->acquired === 1;
    }

    private function releaseLock(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
        }
    }
}
