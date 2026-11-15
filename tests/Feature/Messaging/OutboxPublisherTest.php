<?php

namespace Tests\Feature\Messaging;

use App\Infrastructure\Messaging\OutboxPublisher;
use App\Lambda\PublishOutboxHandler;
use Aws\Sqs\SqsClient;
use Bref\Context\Context;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OutboxPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services_ms.sqs.service_orders_created_url' => 'https://sqs.test/created.fifo',
            'services_ms.sqs.service_order_status_changed_url' => 'https://sqs.test/status.fifo',
        ]);
    }

    private function outboxEvent(string $eventId, string $group, string $type): void
    {
        DB::table('outbox')->insert([
            'event_id' => $eventId,
            'type' => $type,
            'group_id' => $group,
            'payload' => json_encode(['event_id' => $eventId]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pending(): array
    {
        return DB::table('outbox')->whereNull('published_at')->orderBy('id')->pluck('event_id')->all();
    }

    public function test_routes_events_to_their_queues_and_blocks_failed_groups(): void
    {
        $this->outboxEvent('created-a', 'group-a', 'ServiceOrderCreated');
        $this->outboxEvent('status-b', 'group-b', 'ServiceOrderStatusChanged');
        $this->outboxEvent('status-b2', 'group-b', 'ServiceOrderStatusChanged');
        $this->outboxEvent('status-a', 'group-a', 'ServiceOrderStatusChanged');

        $sent = [];
        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->andReturnUsing(function (array $args) use (&$sent) {
            if ($args['MessageDeduplicationId'] === 'status-b') {
                throw new RuntimeException('SQS unavailable');
            }
            $sent[$args['MessageDeduplicationId']] = $args['QueueUrl'];

            return null;
        });

        $this->assertSame(2, (new OutboxPublisher($client))->publishPending());
        $this->assertSame([
            'created-a' => 'https://sqs.test/created.fifo',
            'status-a' => 'https://sqs.test/status.fifo',
        ], $sent);
        // O grupo b fica bloqueado a partir do evento que falhou, preservando a ordem.
        $this->assertSame(['status-b', 'status-b2'], $this->pending());
    }

    public function test_unknown_event_type_blocks_only_its_group(): void
    {
        $this->outboxEvent('unknown-a', 'group-a', 'Unknown');
        $this->outboxEvent('created-a', 'group-a', 'ServiceOrderCreated');
        $this->outboxEvent('created-b', 'group-b', 'ServiceOrderCreated');

        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->once();

        $this->assertSame(1, (new OutboxPublisher($client))->publishPending());
        $this->assertSame(['unknown-a', 'created-a'], $this->pending());
    }

    public function test_lambda_handler_publishes_and_stops_before_the_timeout(): void
    {
        $this->outboxEvent('created-a', 'group-a', 'ServiceOrderCreated');

        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('sendMessage')->once();
        $handler = new PublishOutboxHandler(new OutboxPublisher($client), sleepSeconds: 0);
        $context = new Context('request', (int) (microtime(true) * 1000) + 10_100, 'arn:test', '');

        $this->assertSame(['published' => 1], $handler->handle([], $context));
        $this->assertSame([], $this->pending());
    }
}
