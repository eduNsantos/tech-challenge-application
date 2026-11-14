<?php

namespace App\Console\Commands;

use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishOutbox extends Command
{
    protected $signature = 'outbox:publish {--once : Publish pending events and exit} {--sleep=2 : Seconds between polls}';

    protected $description = 'Publish pending outbox events to their SQS FIFO queues';

    public function handle(): int
    {
        foreach ([
            'SQS_SERVICE_ORDERS_CREATED_URL' => config('services_ms.sqs.service_orders_created_url'),
        ] as $name => $url) {
            if (! $url) {
                $this->error("{$name} is not configured.");

                return self::FAILURE;
            }
        }

        $client = $this->client();

        do {
            $published = $this->publishPending($client);

            if (! $this->option('once') && $published === 0) {
                sleep((int) $this->option('sleep'));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function publishPending(SqsClient $client): int
    {
        $events = DB::table('outbox')->whereNull('published_at')->orderBy('id')->limit(50)->get();
        $published = 0;
        $blockedGroups = [];

        foreach ($events as $event) {
            // Preserva a ordem por O.S.: se um evento falhar, os seguintes do mesmo grupo esperam.
            if (isset($blockedGroups[$event->group_id])) {
                continue;
            }

            $url = match ($event->type) {
                'ServiceOrderCreated' => config('services_ms.sqs.service_orders_created_url'),
                'ServiceOrderApprovalChanged' => config('services_ms.sqs.service_orders_created_url'),
                default => null,
            };

            if (! $url) {
                report(new \RuntimeException("No SQS destination configured for outbox event type {$event->type}."));
                $blockedGroups[$event->group_id] = true;

                continue;
            }

            try {
                $client->sendMessage([
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

    private function client(): SqsClient
    {
        $config = ['version' => 'latest', 'region' => config('services_ms.sqs.region')];

        if ($endpoint = config('services_ms.sqs.endpoint')) {
            $config['endpoint'] = $endpoint;
        }

        return new SqsClient($config);
    }
}
