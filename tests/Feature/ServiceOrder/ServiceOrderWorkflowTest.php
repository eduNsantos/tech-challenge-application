<?php

namespace Tests\Feature\ServiceOrder;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workflow completo de uma Ordem de Serviço:
 *
 * [Atendente] Cadastra veículo e abre OS
 *      ↓
 * [Notificação 1] Mecânico notificado por e-mail
 *      ↓
 * [Mecânico] Faz levantamento (serviços + peças) e envia orçamento
 *      ↓
 * [Notificação 2] Cliente notificado por e-mail com o orçamento
 *      ↓
 * [Atendente] Aprova orçamento em nome do cliente → baixa automática de estoque
 *      ↓
 * [Mecânico] Finaliza o serviço
 *      ↓
 * [Notificação 3] Cliente notificado por e-mail que o veículo está pronto
 *      ↓
 * [Atendente] Registra entrega do veículo (OS entregue)
 */
class ServiceOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const ATENDENTE_EMAIL = 'diego@encinterativa.com.br';
    private const MECANICO_EMAIL  = 'diego.fenille@gmail.com';
    private const ATENDENTE_CPF   = '52998224725';
    private const MECANICO_CPF    = '11122233344';

    private User $atendente;
    private User $mecanico;
    private string $vehicleId;
    private string $serviceId;
    private string $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationServiceInterface::class)
            ->shouldReceive('send')
            ->andReturn(null);

        $this->atendente = User::factory()->create([
            'name'     => 'Diego Fenill',
            'email'    => self::ATENDENTE_EMAIL,
            'document' => self::ATENDENTE_CPF,
            'role'     => 'atendente',
        ]);

        $this->mecanico = User::factory()->create([
            'name'     => 'Diego Fenille',
            'email'    => self::MECANICO_EMAIL,
            'document' => self::MECANICO_CPF,
            'role'     => 'mecanico',
        ]);

        // Cadastra serviço e peça como atendente (pré-condição do workflow)
        $this->serviceId = $this->actingAs($this->atendente, 'api')
            ->postJson('/api/service', [
                'name'  => 'Troca de óleo',
                'price' => 199.90,
            ])->json('id');

        $this->itemId = $this->actingAs($this->atendente, 'api')
            ->postJson('/api/item', [
                'name'             => 'Filtro de óleo',
                'code'             => 'FLT-001',
                'type'             => 'part',
                'measure_unit'     => 'un',
                'minimum_quantity' => 5,
                'unit_price'       => 35.00,
            ])->json('id');

        $this->actingAs($this->atendente, 'api')
            ->postJson("/api/item/{$this->itemId}/stock/entry", [
                'quantity' => 10,
                'reason'   => 'Compra inicial NF-001',
            ]);

        $this->app['auth']->forgetGuards();
    }

    public function test_complete_service_order_workflow(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // PASSO 1 — Atendente cadastra o veículo
        // ─────────────────────────────────────────────────────────────────────
        $vehicleResponse = $this->actingAs($this->atendente, 'api')
            ->postJson('/api/vehicle', [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year'  => 2020,
                'plate' => 'ABC1D23',
            ])
            ->assertOk()
            ->assertJsonStructure(['id', 'message']);

        $this->vehicleId = (string) $vehicleResponse->json('id');

        $this->app['auth']->forgetGuards();

        // ─────────────────────────────────────────────────────────────────────
        // PASSO 2 — Atendente abre a Ordem de Serviço
        // ─────────────────────────────────────────────────────────────────────
        $osResponse = $this->actingAs($this->atendente, 'api')
            ->postJson('/api/service-order', [
                'vehicle_id'    => $this->vehicleId,
                'services'      => [
                    ['service_id' => $this->serviceId, 'quantity' => 1],
                ],
                'send_quote'    => false,
            ])
            ->assertCreated()
            ->assertJsonPath('service_order.status', 'recebida')
            ->assertJsonStructure([
                'service_order' => ['id', 'status', 'customer_id', 'vehicle_id'],
                'message',
            ]);

        $osId = $osResponse->json('service_order.id');

        $this->app['auth']->forgetGuards();

        // ─────────────────────────────────────────────────────────────────────
        // NOTIFICAÇÃO 1 — Mecânico notificado da nova OS
        // ─────────────────────────────────────────────────────────────────────
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => self::MECANICO_EMAIL,
            'subject'      => 'Nova ordem de serviço aberta',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // PASSO 3 — Mecânico faz o levantamento (registra serviços e peças)
        // ─────────────────────────────────────────────────────────────────────
        $this->actingAs($this->mecanico, 'api')
            ->putJson("/api/service-order/{$osId}", [
                'services' => [
                    ['service_id' => $this->serviceId, 'quantity' => 1],
                ],
                'parts' => [
                    ['item_id' => $this->itemId, 'quantity' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('service_order.id', $osId);

        $this->app['auth']->forgetGuards();

        // ─────────────────────────────────────────────────────────────────────
        // PASSO 4 — Mecânico envia orçamento ao cliente
        // ─────────────────────────────────────────────────────────────────────
        $this->actingAs($this->mecanico, 'api')
            ->putJson("/api/service-order/{$osId}", [
                'send_quote' => true,
            ])
            ->assertOk()
            ->assertJsonPath('service_order.status', 'aguardando_aprovacao')
            ->assertJsonPath('service_order.quote_sent_at', fn ($v) => $v !== null);

        $this->app['auth']->forgetGuards();

        // ─────────────────────────────────────────────────────────────────────
        // NOTIFICAÇÃO 2 — Cliente notificado do orçamento
        // ─────────────────────────────────────────────────────────────────────
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => self::ATENDENTE_EMAIL, // cliente criado a partir do atendente
            'subject'      => 'Orçamento da sua ordem de serviço disponível',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // PASSO 5 — Atendente aprova orçamento em nome do cliente
        //           → baixa automática de estoque das peças
        // ─────────────────────────────────────────────────────────────────────
        $this->actingAs($this->atendente, 'api')
            ->putJson("/api/service-order/{$osId}", [
                'approve_quote' => true,
            ])
            ->assertOk()
            ->assertJsonPath('service_order.status', 'em_execucao')
            ->assertJsonPath('service_order.quote_approved_at', fn ($v) => $v !== null);

        // Verifica baixa automática de estoque (10 - 2 = 8 unidades)
        $this->assertDatabaseHas('stock_movements', [
            'item_id'       => $this->itemId,
            'movement_type' => 'withdrawal',
        ]);

        $this->actingAs($this->atendente, 'api')
            ->getJson("/api/item/{$this->itemId}")
            ->assertOk()
            ->assertJsonPath('stock_quantity', fn ($v) => (float) $v === 8.0);

        $this->app['auth']->forgetGuards();

        // ─────────────────────────────────────────────────────────────────────
        // PASSO 6 — Mecânico finaliza o serviço
        // ─────────────────────────────────────────────────────────────────────
        $this->actingAs($this->mecanico, 'api')
            ->patchJson("/api/service-order/{$osId}/status", [
                'status' => 'finalizada',
            ])
            ->assertOk()
            ->assertJsonPath('service_order.status', 'finalizada');

        $this->app['auth']->forgetGuards();

        // ─────────────────────────────────────────────────────────────────────
        // NOTIFICAÇÃO 3 — Cliente notificado que o veículo está pronto
        // ─────────────────────────────────────────────────────────────────────
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => self::ATENDENTE_EMAIL,
            'subject'      => 'Sua ordem de serviço foi concluída',
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // PASSO 7 — Atendente registra a entrega do veículo ao cliente
        // ─────────────────────────────────────────────────────────────────────
        $this->actingAs($this->atendente, 'api')
            ->patchJson("/api/service-order/{$osId}/status", [
                'status' => 'entregue',
            ])
            ->assertOk()
            ->assertJsonPath('service_order.status', 'entregue');

        // ─────────────────────────────────────────────────────────────────────
        // Estado final: OS entregue
        // Total: 5 notificações (3 do workflow + 2 do handler genérico de status)
        // ─────────────────────────────────────────────────────────────────────
        $this->assertDatabaseCount('notifications', 5);
    }
}
