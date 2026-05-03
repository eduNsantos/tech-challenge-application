<?php

namespace Tests\Feature\ServiceOrder;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $vehicleId;
    private string $serviceId;
    private string $itemId;

    // CPF válido para testes
    private const VALID_CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['document' => self::VALID_CPF]);

        $this->serviceId = $this->actingAs($this->user, 'api')
            ->postJson('/api/service', [
                'name'  => 'Troca de óleo',
                'price' => 150.0,
            ])
            ->json('id');

        $this->itemId = $this->actingAs($this->user, 'api')
            ->postJson('/api/item', [
                'name'             => 'Filtro de óleo',
                'code'             => 'FLT-001',
                'type'             => 'part',
                'measure_unit'     => 'un',
                'minimum_quantity' => 2,
                'unit_price'       => 35.0,
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

        // Adiciona estoque ao item para testes que envolvem baixa
        $this->actingAs($this->user, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [
                'quantity' => 50,
                'reason'   => 'Estoque inicial para testes',
            ]);

        $this->app['auth']->forgetGuards();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'vehicle_id'    => $this->vehicleId,
            'services'      => [
                ['service_id' => $this->serviceId, 'quantity' => 1],
            ],
            'items'         => [
                ['item_id' => $this->itemId, 'quantity' => 1],
            ],
            'send_quote'    => false,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // POST /api/service-order — fluxo principal
    // -------------------------------------------------------------------------

    public function test_creates_service_order_and_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'service_order' => [
                    'id', 'customer_id', 'customer_document', 'vehicle_id',
                    'services', 'items', 'status',
                    'services_total', 'items_total', 'total_budget',
                    'quote_sent_at', 'quote_approved_at',
                ],
                'message',
            ])
            ->assertJsonFragment([
                'status'           => 'recebida',
                'customer_document' => self::VALID_CPF,
                'message'          => 'Ordem de servico criada com sucesso',
            ]);
    }

    public function test_returns_422_when_items_are_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['items']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_creates_order_with_items(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'items' => [
                    ['item_id' => $this->itemId, 'quantity' => 2],
                ],
            ]));

        $response->assertStatus(201);

        $items = $response->json('service_order.items');
        $this->assertCount(1, $items);
        $this->assertSame($this->itemId, $items[0]['item_id']);
        $this->assertSame('Filtro de óleo', $items[0]['name']);
        $this->assertEquals(2, $items[0]['quantity']);
    }

    public function test_snapshots_service_data_from_catalog(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'services' => [['service_id' => $this->serviceId, 'quantity' => 1]],
            ]));

        $response->assertStatus(201);

        $service = $response->json('service_order.services.0');
        $this->assertSame($this->serviceId, $service['service_id']);
        $this->assertSame('Troca de óleo', $service['name']);
        $this->assertEquals(150, $service['unit_price']);
    }

    public function test_calculates_totals_correctly(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'services' => [['service_id' => $this->serviceId, 'quantity' => 2]],  // 2 × 150 = 300
                'items'    => [['item_id' => $this->itemId, 'quantity' => 3]],        // 3 × 35  = 105
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('service_order.services_total', 300)
            ->assertJsonPath('service_order.items_total', 105)
            ->assertJsonPath('service_order.total_budget', 405);
    }

    // -------------------------------------------------------------------------
    // send_quote
    // -------------------------------------------------------------------------

    public function test_status_is_recebida_when_send_quote_is_false(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload(['send_quote' => false]))
            ->assertStatus(201)
            ->assertJsonPath('service_order.status', 'recebida')
            ->assertJsonPath('service_order.quote_sent_at', null);
    }

    public function test_status_is_aguardando_aprovacao_when_send_quote_is_true(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload(['send_quote' => true]));

        $response->assertStatus(201)
            ->assertJsonPath('service_order.status', 'aguardando_aprovacao');

        $this->assertNotNull($response->json('service_order.quote_sent_at'));
    }

    // -------------------------------------------------------------------------
    // Persistência no banco
    // -------------------------------------------------------------------------

    public function test_persists_service_order_to_database(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload());

        $id = $response->json('service_order.id');

        $this->assertDatabaseHas('service_orders', [
            'id'                => $id,
            'customer_document' => self::VALID_CPF,
            'status'            => 'recebida',
        ]);
    }

    public function test_persists_services_lines_to_database(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'services' => [
                    ['service_id' => $this->serviceId, 'quantity' => 2],
                ],
            ]));

        $orderId = $response->json('service_order.id');

        $this->assertDatabaseHas('service_order_services', [
            'service_order_id' => $orderId,
            'service_id' => $this->serviceId,
            'quantity' => 2,
            'price' => 150.0,
        ]);
    }

    public function test_persists_customer_to_database_when_new(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload());

        $this->assertDatabaseHas('customers', [
            'document' => self::VALID_CPF,
        ]);
    }

    public function test_persists_vehicle_reference_on_service_order(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload());

        $this->assertDatabaseHas('service_orders', [
            'id' => $response->json('service_order.id'),
            'vehicle_id' => $this->vehicleId,
        ]);
    }

    public function test_does_not_create_new_vehicle_when_creating_order(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload());

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload());

        $this->assertDatabaseCount('vehicles', 1);
    }

    // -------------------------------------------------------------------------
    // Autenticação
    // -------------------------------------------------------------------------

    public function test_returns_401_without_authentication(): void
    {
        $this->postJson('/api/service-order', $this->validPayload())
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Validação de entrada (422)
    // -------------------------------------------------------------------------

    public function test_returns_422_when_vehicle_id_is_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['vehicle_id']);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_returns_422_when_services_array_is_empty(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload(['services' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['services']);
    }

    public function test_returns_422_when_service_id_is_not_uuid(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'services' => [['service_id' => 'nao-e-uuid', 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['services.0.service_id']);
    }

    public function test_returns_422_when_service_quantity_is_zero(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'services' => [['service_id' => $this->serviceId, 'quantity' => 0]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['services.0.quantity']);
    }

    public function test_returns_422_when_item_id_is_not_uuid(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'items' => [['item_id' => 'nao-e-uuid', 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.item_id']);
    }

    // -------------------------------------------------------------------------
    // Erros de domínio (422)
    // -------------------------------------------------------------------------

    public function test_returns_422_when_service_id_does_not_exist(): void
    {
        $uuidInexistente = '00000000-0000-0000-0000-000000000000';

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'services' => [['service_id' => $uuidInexistente, 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['services.0.service_id']);
    }

    public function test_returns_422_when_item_id_does_not_exist(): void
    {
        $uuidInexistente = '00000000-0000-0000-0000-000000000000';

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', $this->validPayload([
                'items' => [['item_id' => $uuidInexistente, 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.item_id']);
    }
}
