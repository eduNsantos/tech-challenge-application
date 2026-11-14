<?php

namespace Tests\Feature\Messaging;

use App\Application\ServiceOrder\ProcessServiceOrderStatusChanged;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Models\User;
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

    private function statusPayload(string $orderId, string $status, array $overrides = []): array
    {
        return array_merge([
            'event_id' => (string) Str::uuid(),
            'type' => 'ServiceOrderStatusChanged',
            'version' => 1,
            'occurred_at' => now()->addMinute()->toIso8601String(),
            'service_order_id' => $orderId,
            'status' => $status,
            'previous_status' => 'recebida',
        ], $overrides);
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

    public function test_backfill_is_idempotent_and_skips_orders_already_in_outbox(): void
    {
        config(['services_ms.enabled' => true]);
        $this->createServiceOrder();

        Artisan::call('services-ms:backfill-service-orders');
        Artisan::call('services-ms:backfill-service-orders');

        $this->assertSame(1, DB::table('outbox')->where('type', 'ServiceOrderCreated')->count());
    }

    public function test_applies_status_change_and_dispatches_event(): void
    {
        $orderId = $this->createServiceOrder();
        Event::fake([ServiceOrderStatusChanged::class]);

        $applied = app(ProcessServiceOrderStatusChanged::class)
            ->handle($this->statusPayload($orderId, 'em_execucao'));

        $this->assertTrue($applied);
        $this->assertSame('em_execucao', DB::table('service_orders')->where('id', $orderId)->value('status'));
        Event::assertDispatched(ServiceOrderStatusChanged::class, fn ($e) => $e->oldStatus === 'recebida');
    }

    public function test_keeps_service_ids_when_applying_status(): void
    {
        $orderId = $this->createServiceOrder();
        $before = DB::table('service_order_services')->where('service_order_id', $orderId)->pluck('id');

        app(ProcessServiceOrderStatusChanged::class)->handle($this->statusPayload($orderId, 'em_execucao'));

        $this->assertEquals($before, DB::table('service_order_services')->where('service_order_id', $orderId)->pluck('id'));
    }

    public function test_ignores_duplicate_event(): void
    {
        $orderId = $this->createServiceOrder();
        $payload = $this->statusPayload($orderId, 'em_execucao');
        $processor = app(ProcessServiceOrderStatusChanged::class);

        $this->assertTrue($processor->handle($payload));
        $this->assertFalse($processor->handle($payload));
    }

    public function test_ignores_stale_event(): void
    {
        $orderId = $this->createServiceOrder();

        $applied = app(ProcessServiceOrderStatusChanged::class)
            ->handle($this->statusPayload($orderId, 'em_execucao', ['occurred_at' => now()->subHour()->toIso8601String()]));

        $this->assertFalse($applied);
        $this->assertSame('recebida', DB::table('service_orders')->where('id', $orderId)->value('status'));
    }

    public function test_ignores_unknown_service_order(): void
    {
        $applied = app(ProcessServiceOrderStatusChanged::class)
            ->handle($this->statusPayload((string) Str::uuid(), 'em_execucao'));

        $this->assertFalse($applied);
    }

    public function test_rejects_invalid_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProcessServiceOrderStatusChanged::class)->handle(['event_id' => 'x']);
    }

    public function test_rejects_unsupported_status_event_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProcessServiceOrderStatusChanged::class)
            ->handle($this->statusPayload((string) Str::uuid(), 'em_execucao', ['version' => 2]));
    }
}
