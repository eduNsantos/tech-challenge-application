<?php

namespace Tests\Feature\ServiceOrder;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateAndRemoveServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CPF = '52998224725';

    private User $user;
    private string $customerId;
    private string $customer2Id;
    private string $serviceId;
    private string $service2Id;
    private string $itemId;
    private string $item2Id;
    private string $vehicleId;
    private string $vehicle2Id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['document' => self::VALID_CPF]);

        $this->customerId = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $this->customerId,
            'name' => 'Cliente Teste 1',
            'email' => 'cliente1@example.com',
            'phone' => '11999990000',
            'document' => self::VALID_CPF,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customer2Id = Str::uuid()->toString();
        DB::table('customers')->insert([
            'id' => $this->customer2Id,
            'name' => 'Cliente Teste 2',
            'email' => 'cliente2@example.com',
            'phone' => '11999990001',
            'document' => '12345678909',
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', [
                'name'  => 'Troca de oleo',
                'price' => 150.0,
            ])
            ->json('id');

        $this->service2Id = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', [
                'name'  => 'Alinhamento',
                'price' => 90.0,
            ])
            ->json('id');

        $this->itemId = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', [
                'name'             => 'Filtro de oleo',
                'code'             => 'FLT-TEST-001',
                'type'             => 'part',
                'measure_unit'     => 'un',
                'minimum_quantity' => 2,
                'unit_price'       => 35.0,
            ])
            ->json('id');

        $this->item2Id = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', [
                'name'             => 'Pastilha de freio',
                'code'             => 'FLT-TEST-002',
                'type'             => 'part',
                'measure_unit'     => 'un',
                'minimum_quantity' => 2,
                'unit_price'       => 50.0,
            ])
            ->json('id');

        $this->vehicleId = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year'  => 2020,
                'plate' => 'ABC1D23',
            ])
            ->json('id');

        $this->vehicle2Id = $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', [
                'brand' => 'Honda',
                'model' => 'Civic',
                'year'  => 2021,
                'plate' => 'XYZ1A23',
            ])
            ->json('id');

        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [
                'quantity' => 50,
                'reason'   => 'Estoque inicial item 1',
            ]);

        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->item2Id}/stock/entry", [
                'quantity' => 50,
                'reason'   => 'Estoque inicial item 2',
            ]);

        $this->app['auth']->forgetGuards();
    }

    private function createServiceOrder(): string
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', [
                'customer_id' => $this->customerId,
                'vehicle_id' => $this->vehicleId,
                'services' => [
                    ['service_id' => $this->serviceId, 'quantity' => 1],
                ],
                'items' => [
                    ['item_id' => $this->itemId, 'quantity' => 2],
                ],
                'send_quote' => false,
            ])
            ->assertCreated();

        return (string) $response->json('service_order.id');
    }

    public function test_updates_services_replacing_previous_lines(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/service-order/{$serviceOrderId}", [
                'services' => [
                    ['service_id' => $this->service2Id, 'quantity' => 5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('service_order.services.0.service_id', $this->service2Id)
            ->assertJsonPath('service_order.services.0.quantity', 5)
            ->assertJsonPath('service_order.services_total', 450);

        $this->assertDatabaseHas('service_order_services', [
            'service_order_id' => $serviceOrderId,
            'service_id' => $this->service2Id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('service_order_services', [
            'service_order_id' => $serviceOrderId,
            'service_id' => $this->serviceId,
        ]);
    }

    public function test_updates_items_replacing_previous_lines(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/service-order/{$serviceOrderId}", [
                'items' => [
                    ['item_id' => $this->item2Id, 'quantity' => 5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('service_order.items.0.item_id', $this->item2Id)
            ->assertJsonPath('service_order.items.0.quantity', 5)
            ->assertJsonPath('service_order.items_total', 250);

        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $serviceOrderId,
            'item_id' => $this->item2Id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('service_order_items', [
            'service_order_id' => $serviceOrderId,
            'item_id' => $this->itemId,
        ]);
    }

    public function test_updates_vehicle_when_vehicle_id_is_provided(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/service-order/{$serviceOrderId}", [
                'vehicle_id' => $this->vehicle2Id,
            ])
            ->assertOk()
            ->assertJsonPath('service_order.vehicle_id', $this->vehicle2Id);

        $this->assertDatabaseHas('service_orders', [
            'id' => $serviceOrderId,
            'vehicle_id' => $this->vehicle2Id,
        ]);
    }

    public function test_updates_customer_when_customer_id_is_provided(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/service-order/{$serviceOrderId}", [
                'customer_id' => $this->customer2Id,
            ])
            ->assertOk()
            ->assertJsonPath('service_order.customer_id', $this->customer2Id);

        $this->assertDatabaseHas('service_orders', [
            'id' => $serviceOrderId,
            'customer_id' => $this->customer2Id,
        ]);
    }

    public function test_approve_quote_changes_status_and_withdraws_stock(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/service-order/{$serviceOrderId}", [
                'approve_quote' => true,
            ])
            ->assertOk()
            ->assertJsonPath('service_order.status', 'em_execucao')
            ->assertJsonPath('service_order.quote_approved_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->itemId,
            'movement_type' => 'withdrawal',
            'service_order_id' => $serviceOrderId,
        ]);

        $this->actingAs($this->user, 'api')
            ->getJson("/api/item/{$this->itemId}")
            ->assertOk()
            ->assertJsonPath('stock_quantity', fn ($value) => (float) $value === 48.0);
    }

    public function test_removes_service_line_from_service_order(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/service-order/{$serviceOrderId}/services/{$this->serviceId}")
            ->assertOk()
            ->assertJsonCount(0, 'service_order.services')
            ->assertJsonPath('service_order.services_total', 0)
            ->assertJsonPath('service_order.total_budget', 70);

        $this->assertDatabaseMissing('service_order_services', [
            'service_order_id' => $serviceOrderId,
            'service_id' => $this->serviceId,
        ]);
    }

    public function test_removes_item_line_from_service_order(): void
    {
        $serviceOrderId = $this->createServiceOrder();

        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/service-order/{$serviceOrderId}/items/{$this->itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'service_order.items')
            ->assertJsonPath('service_order.items_total', 0)
            ->assertJsonPath('service_order.total_budget', 150);

        $this->assertDatabaseMissing('service_order_items', [
            'service_order_id' => $serviceOrderId,
            'item_id' => $this->itemId,
        ]);
    }
}
