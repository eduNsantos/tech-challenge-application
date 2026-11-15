<?php

namespace Tests\Feature\Messaging;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Application\ServiceOrder\ProcessServiceOrderExecution;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderStatusUseCase;
use App\Lambda\ServiceOrderExecutionHandler;
use Bref\Context\Context;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ServiceOrderMessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $customerId;

    private string $vehicleId;

    private string $serviceId;

    private string $itemId;

    private const VALID_CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['document' => self::VALID_CPF]);

        $this->customerId = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $this->customerId,
            'name' => 'Cliente Teste',
            'email' => 'cliente.teste@example.com',
            'phone' => '11999990000',
            'document' => self::VALID_CPF,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', ['name' => 'Troca de óleo', 'price' => 150.0])
            ->json('id');

        $this->itemId = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', [
                'name' => 'Filtro de óleo',
                'code' => 'FLT-001',
                'type' => 'part',
                'measure_unit' => 'un',
                'minimum_quantity' => 2,
                'unit_price' => 35.0,
            ])
            ->json('id');

        $this->vehicleId = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', ['brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2020, 'plate' => 'ABC1D23'])
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", ['quantity' => 50, 'reason' => 'Estoque inicial']);

        $this->app['auth']->forgetGuards();
    }

    private function createServiceOrder(): string
    {
        return $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', [
                'customer_id' => $this->customerId,
                'vehicle_id' => $this->vehicleId,
                'services' => [['service_id' => $this->serviceId, 'quantity' => 2]],
                'items' => [['item_id' => $this->itemId, 'quantity' => 1]],
                'send_quote' => false,
            ])
            ->assertCreated()
            ->json('service_order.id');
    }

    public function test_records_created_event_in_outbox_with_services_only(): void
    {
        config(['services_ms.enabled' => true]);

        $orderId = $this->createServiceOrder();

        $row = DB::table('outbox')->first();
        $this->assertSame($orderId, $row->group_id);
        $this->assertNull($row->published_at);

        $payload = json_decode($row->payload, true);
        $this->assertSame('ServiceOrderCreated', $payload['type']);
        $this->assertSame($row->id, $payload['sequence']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame($orderId, $payload['service_order']['id']);
        $this->assertSame($this->customerId, $payload['service_order']['customer_id']);
        $this->assertSame(300.0, (float) $payload['service_order']['services_total']);
        $this->assertCount(1, $payload['services']);
        $this->assertSame($this->serviceId, $payload['services'][0]['service_id']);
        $this->assertEquals(150, $payload['services'][0]['price']);
        $this->assertSame(
            DB::table('service_order_services')->where('service_order_id', $orderId)->value('id'),
            $payload['services'][0]['id']
        );
        $this->assertArrayNotHasKey('items', $payload);
    }

    public function test_does_not_write_outbox_when_disabled(): void
    {
        config(['services_ms.enabled' => false]);

        $this->createServiceOrder();

        $this->assertSame(0, DB::table('outbox')->count());
    }

    public function test_status_changes_are_published_and_execution_completion_is_applied_once(): void
    {
        config(['services_ms.enabled' => true]);
        Event::fake();
        $orderId = $this->createServiceOrder();
        app(UpdateServiceOrderStatusUseCase::class)->execute(
            new UpdateServiceOrderStatusDTO($orderId, 'em_execucao')
        );
        $row = DB::table('outbox')->where('type', 'ServiceOrderStatusChanged')->first();
        $status = json_decode($row->payload, true);
        $this->assertSame('recebida', $status['previous_status']);
        $this->assertSame('em_execucao', $status['status']);
        $this->assertSame($row->id, $status['sequence']);

        $event = [
            'event_id' => (string) Str::uuid(),
            'type' => 'ServiceOrderExecutionFinished',
            'version' => 1,
            'service_order_id' => $orderId,
            'status_sequence' => $status['sequence'],
        ];
        $processor = app(ProcessServiceOrderExecution::class);
        $this->assertTrue($processor->handle($event));
        $this->assertFalse($processor->handle($event));
        $this->assertDatabaseHas('service_orders', ['id' => $orderId, 'status' => 'finalizada']);
        $this->assertSame(2, DB::table('outbox')->where('type', 'ServiceOrderStatusChanged')->count());
    }

    public function test_backfill_is_idempotent_and_skips_orders_already_in_outbox(): void
    {
        config(['services_ms.enabled' => true]);
        $this->createServiceOrder();

        Artisan::call('services-ms:backfill-service-orders');
        Artisan::call('services-ms:backfill-service-orders');

        $this->assertSame(1, DB::table('outbox')->where('type', 'ServiceOrderCreated')->count());
    }

    private function executionEvent(string $orderId, string $type, int $sequence): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'type' => $type,
            'version' => 1,
            'service_order_id' => $orderId,
            'status_sequence' => $sequence,
        ];
    }

    private function startExecution(string $orderId): int
    {
        app(UpdateServiceOrderStatusUseCase::class)->execute(
            new UpdateServiceOrderStatusDTO($orderId, 'em_execucao')
        );

        return (int) DB::table('outbox')->where('group_id', $orderId)->max('id');
    }

    public function test_execution_started_does_not_change_status(): void
    {
        config(['services_ms.enabled' => true]);
        Event::fake();
        $orderId = $this->createServiceOrder();
        $sequence = $this->startExecution($orderId);

        $applied = app(ProcessServiceOrderExecution::class)
            ->handle($this->executionEvent($orderId, 'ServiceOrderExecutionStarted', $sequence));

        $this->assertFalse($applied);
        $this->assertDatabaseHas('service_orders', ['id' => $orderId, 'status' => 'em_execucao']);
    }

    public function test_ignores_execution_finished_computed_from_an_outdated_status(): void
    {
        config(['services_ms.enabled' => true]);
        Event::fake();
        $orderId = $this->createServiceOrder();
        $sequence = $this->startExecution($orderId);

        $applied = app(ProcessServiceOrderExecution::class)
            ->handle($this->executionEvent($orderId, 'ServiceOrderExecutionFinished', $sequence - 1));

        $this->assertFalse($applied);
        $this->assertDatabaseHas('service_orders', ['id' => $orderId, 'status' => 'em_execucao']);
    }

    public function test_execution_event_for_unknown_order_is_retried(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(ProcessServiceOrderExecution::class)
            ->handle($this->executionEvent((string) Str::uuid(), 'ServiceOrderExecutionFinished', 1));
    }

    private function serviceEvent(string $orderId, string $type, int $sequence, array $data): array
    {
        return $this->executionEvent($orderId, $type, $sequence) + $data + [
            'service_order_service_id' => DB::table('service_order_services')->where('service_order_id', $orderId)->value('id'),
            'user_id' => (string) $this->user->id,
        ];
    }

    public function test_service_start_and_finish_from_services_ms_update_the_service(): void
    {
        config(['services_ms.enabled' => true]);
        Event::fake();
        $orderId = $this->createServiceOrder();
        $sequence = $this->startExecution($orderId);
        $processor = app(ProcessServiceOrderExecution::class);

        $started = $this->serviceEvent($orderId, 'ServiceOrderServiceStarted', $sequence, ['started_at' => '2026-11-16T10:00:00+00:00']);
        $this->assertTrue($processor->handle($started));
        $this->assertFalse($processor->handle($started));

        $this->assertTrue($processor->handle(
            $this->serviceEvent($orderId, 'ServiceOrderServiceFinished', $sequence, ['finished_at' => '2026-11-16T10:30:00+00:00'])
        ));

        $row = DB::table('service_order_services')->where('service_order_id', $orderId)->first();
        $this->assertSame($this->user->id, (int) $row->started_user_id);
        $this->assertSame($this->user->id, (int) $row->finished_user_id);
        $this->assertNotNull($row->started_at);
        $this->assertNotNull($row->finished_at);
        // Evento de serviço não muda o status da O.S.; quem faz isso é o ServiceOrderExecutionFinished.
        $this->assertDatabaseHas('service_orders', ['id' => $orderId, 'status' => 'em_execucao']);

        $this->actingAs($this->user, 'api')
            ->getJson("/api/service-order/{$orderId}")
            ->assertOk()
            ->assertJsonPath('service_order.services.0.started_at', $row->started_at)
            ->assertJsonPath('service_order.services.0.finished_at', $row->finished_at);
    }

    public function test_service_execution_survives_later_service_order_updates(): void
    {
        config(['services_ms.enabled' => true]);
        Event::fake();
        $orderId = $this->createServiceOrder();
        $sequence = $this->startExecution($orderId);
        app(ProcessServiceOrderExecution::class)->handle(
            $this->serviceEvent($orderId, 'ServiceOrderServiceStarted', $sequence, ['started_at' => '2026-11-16T10:00:00+00:00'])
        );

        $repository = app(\App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface::class);
        $repository->update($repository->findById($orderId));

        $this->assertNotNull(DB::table('service_order_services')->where('service_order_id', $orderId)->value('started_at'));
    }

    public function test_service_event_for_service_removed_from_order_is_ignored(): void
    {
        config(['services_ms.enabled' => true]);
        $orderId = $this->createServiceOrder();
        $sequence = $this->startExecution($orderId);

        $applied = app(ProcessServiceOrderExecution::class)->handle(
            $this->serviceEvent($orderId, 'ServiceOrderServiceStarted', $sequence, [
                'started_at' => '2026-11-16T10:00:00+00:00',
                'service_order_service_id' => (string) Str::uuid(),
            ])
        );

        $this->assertFalse($applied);
        $this->assertDatabaseHas('processed_messages', ['type' => 'ServiceOrderServiceStarted']);
    }

    public function test_rejects_service_event_without_service_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProcessServiceOrderExecution::class)->handle(
            $this->executionEvent((string) Str::uuid(), 'ServiceOrderServiceStarted', 1) + ['started_at' => '2026-11-16T10:00:00+00:00']
        );
    }

    public function test_rejects_invalid_execution_event(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProcessServiceOrderExecution::class)->handle(['event_id' => 'x']);
    }

    public function test_status_sync_publishes_current_status_of_imported_orders(): void
    {
        config(['services_ms.enabled' => true]);
        $imported = $this->createServiceOrder();
        config(['services_ms.enabled' => false]);
        $this->createServiceOrder();
        config(['services_ms.enabled' => true]);

        Artisan::call('services-ms:sync-service-order-status');

        $rows = DB::table('outbox')->where('type', 'ServiceOrderStatusChanged')->get();
        $this->assertCount(1, $rows);
        $payload = json_decode($rows[0]->payload, true);
        $this->assertSame($imported, $payload['service_order_id']);
        $this->assertSame('recebida', $payload['status']);
        $this->assertSame($rows[0]->id, $payload['sequence']);
    }

    public function test_backfill_queues_orders_created_while_integration_was_disabled(): void
    {
        config(['services_ms.enabled' => false]);
        $first = $this->createServiceOrder();
        $second = $this->createServiceOrder();
        config(['services_ms.enabled' => true]);

        Artisan::call('services-ms:backfill-service-orders', ['--chunk' => 1]);

        $this->assertEqualsCanonicalizing(
            [$first, $second],
            DB::table('outbox')->where('type', 'ServiceOrderCreated')->pluck('group_id')->all()
        );
    }

    public function test_status_sync_requires_integration_enabled(): void
    {
        config(['services_ms.enabled' => false]);

        $this->assertSame(1, Artisan::call('services-ms:sync-service-order-status'));
    }

    private function sqsEvent(array $bodies): array
    {
        return ['Records' => array_map(fn ($body, $i) => [
            'messageId' => "msg-{$i}",
            'receiptHandle' => "handle-{$i}",
            'body' => is_string($body) ? $body : json_encode($body),
            'attributes' => ['ApproximateReceiveCount' => '1'],
            'messageAttributes' => [],
            'eventSource' => 'aws:sqs',
        ], $bodies, array_keys($bodies))];
    }

    public function test_execution_lambda_applies_events_and_returns_failures_in_order(): void
    {
        config(['services_ms.enabled' => true]);
        Event::fake();
        $orderId = $this->createServiceOrder();
        $sequence = $this->startExecution($orderId);
        $context = new Context('request', (int) (microtime(true) * 1000) + 30_000, 'arn:test', '');
        $handler = app(ServiceOrderExecutionHandler::class);

        $this->assertNull($handler->handle($this->sqsEvent([
            $this->executionEvent($orderId, 'ServiceOrderExecutionFinished', $sequence),
        ]), $context));
        $this->assertDatabaseHas('service_orders', ['id' => $orderId, 'status' => 'finalizada']);

        // Na primeira falha (JSON inválido), o restante do lote FIFO também volta para a fila.
        $this->assertSame(['batchItemFailures' => [
            ['itemIdentifier' => 'msg-0'],
            ['itemIdentifier' => 'msg-1'],
        ]], $handler->handle($this->sqsEvent([
            'not-json',
            $this->executionEvent($orderId, 'ServiceOrderExecutionStarted', $sequence),
        ]), $context));

        // O.S. desconhecida: a mensagem volta para a fila para nova tentativa.
        $this->assertSame(['batchItemFailures' => [['itemIdentifier' => 'msg-0']]], $handler->handle($this->sqsEvent([
            $this->executionEvent((string) Str::uuid(), 'ServiceOrderExecutionFinished', 1),
        ]), $context));
    }
}
