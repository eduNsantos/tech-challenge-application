<?php

namespace Tests\Feature\Payment;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Domain\Payment\Exceptions\PaymentServiceUnavailableException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CPF = '52998224725';
    private const VALID_CNPJ = '11222333000181';
    private const PAYMENT_LINK = 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=abc';

    private User $user;
    private string $customerId;
    private string $vehicleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationServiceInterface::class)
            ->shouldReceive('send')
            ->andReturn(null);

        $this->user = User::factory()->create(['document' => self::VALID_CPF]);
        $this->customerId = $this->createCustomer(self::VALID_CPF);

        $this->vehicleId = (string) $this->actingAs($this->user, 'api')
            ->postJson('/api/vehicle', [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2020,
                'plate' => 'ABC1D23',
            ])
            ->assertOk()
            ->json('id');

        $this->app['auth']->forgetGuards();
    }

    private function createCustomer(string $document): string
    {
        $id = Str::uuid()->toString();

        DB::table('customers')->insert([
            'id' => $id,
            'name' => 'Cliente Pagamento',
            'email' => "cliente.{$document}@example.com",
            'phone' => '(11) 99999-0000',
            'document' => $document,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createService(string $name, float $price): string
    {
        $id = Str::uuid()->toString();

        DB::table('services')->insert([
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * OS em aguardando_aprovacao com cobrança aberta no payment-service.
     *
     * @return array{id: string, payment_order_id: string, callback_token: string, approval_token: string}
     */
    private function createAwaitingPaymentOrder(?string $paymentLink = null): array
    {
        $order = [
            'id' => Str::uuid()->toString(),
            'payment_order_id' => Str::uuid()->toString(),
            'callback_token' => bin2hex(random_bytes(32)),
            'approval_token' => bin2hex(random_bytes(32)),
        ];

        DB::table('service_orders')->insert([
            'id' => $order['id'],
            'customer_id' => $this->customerId,
            'vehicle_id' => $this->vehicleId,
            'status' => 'aguardando_aprovacao',
            'services_total' => 5.5,
            'parts_total' => 0.0,
            'total_budget' => 5.5,
            'quote_sent_at' => now(),
            'quote_approved_at' => null,
            'approval_token' => $order['approval_token'],
            'payment_order_id' => $order['payment_order_id'],
            'payment_callback_token' => $order['callback_token'],
            'payment_link' => $paymentLink,
            'created_user_id' => $this->user->id,
            'updated_user_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order;
    }

    // ── Envio do orçamento → payment-service ───────────────────────────────────

    public function test_sending_quote_requests_payment_link_with_mandatory_callback_url(): void
    {
        $serviceId = $this->createService('Diagnostico', 5.5);

        $osId = (string) $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', [
                'customer_id' => $this->customerId,
                'vehicle_id' => $this->vehicleId,
                'services' => [['service_id' => $serviceId, 'quantity' => 1]],
                'send_quote' => false,
            ])
            ->assertCreated()
            ->json('service_order.id');

        $this->assertNull($this->paymentGateway->lastRequest());

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/service-order/{$osId}", [
                'services' => [['service_id' => $serviceId, 'quantity' => 1]],
                'send_quote' => true,
            ])
            ->assertOk()
            ->assertJsonPath('service_order.status', 'aguardando_aprovacao')
            ->assertJsonPath('service_order.payment_link', null)
            ->assertJsonMissingPath('approval_link');

        $sent = $this->paymentGateway->lastRequest();
        $paymentOrderId = $response->json('service_order.payment_order_id');

        $this->assertSame($paymentOrderId, $sent['order_id']);
        $this->assertSame(550, $sent['amount_cents']);
        $this->assertSame('BRL', $sent['currency']);
        $this->assertSame([['id' => $serviceId, 'title' => 'Diagnostico', 'quantity' => 1, 'unit_price_cents' => 550]], $sent['items']);
        $this->assertSame(['name' => 'Cliente Pagamento', 'email' => 'cliente.' . self::VALID_CPF . '@example.com', 'cpf' => self::VALID_CPF, 'document_type' => 'CPF', 'phone' => '+5511999990000'], $sent['customer']);
        $this->assertSame($osId, $sent['metadata']['service_order_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $sent['created_at']);

        $token = DB::table('service_orders')->where('id', $osId)->value('payment_callback_token');
        $this->assertSame(url("/api/payment/callback/{$token}"), $sent['callback_url']);
        $this->assertSame(url('/api/payment/notification'), $sent['notification_url']);

        // Sem link ainda → cliente não foi notificado
        $this->assertDatabaseMissing('notifications', ['subject' => 'Orçamento da sua ordem de serviço disponível']);
    }

    public function test_sending_quote_for_cnpj_customer_sends_document_type_cnpj(): void
    {
        $this->customerId = $this->createCustomer(self::VALID_CNPJ);
        $serviceId = $this->createService('Revisao', 6.0);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', [
                'customer_id' => $this->customerId,
                'vehicle_id' => $this->vehicleId,
                'services' => [['service_id' => $serviceId, 'quantity' => 1]],
                'send_quote' => true,
            ])
            ->assertCreated();

        $sent = $this->paymentGateway->lastRequest();
        $this->assertSame(self::VALID_CNPJ, $sent['customer']['cpf']);
        $this->assertSame('CNPJ', $sent['customer']['document_type']);
    }

    public function test_payment_service_failure_returns_502_and_does_not_create_order(): void
    {
        $this->paymentGateway->failWith = new PaymentServiceUnavailableException('fora do ar');
        $serviceId = $this->createService('Revisao', 6.0);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/service-order', [
                'customer_id' => $this->customerId,
                'vehicle_id' => $this->vehicleId,
                'services' => [['service_id' => $serviceId, 'quantity' => 1]],
                'send_quote' => true,
            ])
            ->assertStatus(502);

        $this->assertDatabaseCount('service_orders', 0);
    }

    // ── POST /api/payment/notification (Step ③) ────────────────────────────────

    public function test_notification_stores_link_and_notifies_customer(): void
    {
        $order = $this->createAwaitingPaymentOrder();

        $this->postJson('/api/payment/notification', [
            'order_id' => $order['payment_order_id'],
            'payment_link' => self::PAYMENT_LINK,
            'status' => 'WAITING_PAYMENT',
        ])
            ->assertOk()
            ->assertJsonPath('service_order_id', $order['id'])
            ->assertJsonPath('status', 'aguardando_aprovacao');

        $this->assertDatabaseHas('service_orders', ['id' => $order['id'], 'payment_link' => self::PAYMENT_LINK]);

        $notification = DB::table('notifications')
            ->where('subject', 'Orçamento da sua ordem de serviço disponível')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString(self::PAYMENT_LINK, $notification->content);
        $this->assertStringContainsString("/api/service-order/reject/{$order['approval_token']}", $notification->content);
        $this->assertStringNotContainsString('/approve/', $notification->content);
    }

    public function test_notification_is_not_processed_twice(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $this->postJson('/api/payment/notification', [
            'order_id' => $order['payment_order_id'],
            'payment_link' => 'https://outro-link.example.com/checkout',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('service_orders', ['id' => $order['id'], 'payment_link' => self::PAYMENT_LINK]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_for_unknown_order_returns_422(): void
    {
        $this->postJson('/api/payment/notification', [
            'order_id' => Str::uuid()->toString(),
            'payment_link' => self::PAYMENT_LINK,
        ])->assertUnprocessable();
    }

    public function test_notification_validates_payload(): void
    {
        $this->postJson('/api/payment/notification', ['order_id' => 'nao-uuid'])
            ->assertUnprocessable();
    }

    // ── POST /api/payment/callback/{token} (resultado final) ───────────────────

    public function test_paid_callback_approves_quote(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $this->postJson("/api/payment/callback/{$order['callback_token']}", [
            'order_id' => $order['payment_order_id'],
            'status' => 'PAID',
            'mp_payment_id' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('service_order_id', $order['id'])
            ->assertJsonPath('status', 'em_execucao')
            ->assertJsonPath('quote_approved_at', fn ($v) => $v !== null);

        $this->assertDatabaseHas('service_orders', [
            'id' => $order['id'],
            'status' => 'em_execucao',
            'approval_token' => null,
            'payment_callback_token' => null,
        ]);
    }

    public static function notPaidStatuses(): array
    {
        return [
            'REJECTED' => ['REJECTED'],
            'EXPIRED' => ['EXPIRED'],
            'qualquer outro' => ['CANCELLED'],
        ];
    }

    #[DataProvider('notPaidStatuses')]
    public function test_not_paid_callback_follows_reject_flow(string $status): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $this->postJson("/api/payment/callback/{$order['callback_token']}", [
            'order_id' => $order['payment_order_id'],
            'status' => $status,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'em_diagnostico');

        $this->assertDatabaseHas('service_orders', [
            'id' => $order['id'],
            'status' => 'em_diagnostico',
            'quote_approved_at' => null,
            'approval_token' => null,
            'payment_callback_token' => null,
        ]);
    }

    public function test_callback_with_invalid_token_is_rejected(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $this->postJson('/api/payment/callback/' . bin2hex(random_bytes(32)), [
            'order_id' => $order['payment_order_id'],
            'status' => 'PAID',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('service_orders', ['id' => $order['id'], 'status' => 'aguardando_aprovacao']);
    }

    public function test_callback_with_mismatched_order_id_is_rejected(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $this->postJson("/api/payment/callback/{$order['callback_token']}", [
            'order_id' => Str::uuid()->toString(),
            'status' => 'PAID',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('service_orders', ['id' => $order['id'], 'status' => 'aguardando_aprovacao']);
    }

    public function test_callback_cannot_be_replayed(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);
        $payload = ['order_id' => $order['payment_order_id'], 'status' => 'REJECTED'];

        $this->postJson("/api/payment/callback/{$order['callback_token']}", $payload)->assertOk();

        $this->postJson("/api/payment/callback/{$order['callback_token']}", [
            ...$payload,
            'status' => 'PAID',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('service_orders', ['id' => $order['id'], 'status' => 'em_diagnostico']);
    }

    public function test_reject_link_invalidates_payment_callback(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $this->getJson("/api/service-order/reject/{$order['approval_token']}")->assertOk();

        $this->postJson("/api/payment/callback/{$order['callback_token']}", [
            'order_id' => $order['payment_order_id'],
            'status' => 'PAID',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('service_orders', ['id' => $order['id'], 'status' => 'em_diagnostico']);
    }

    public function test_callback_token_is_not_exposed_in_service_order_listing(): void
    {
        $order = $this->createAwaitingPaymentOrder(self::PAYMENT_LINK);

        $response = $this->actingAs($this->user, 'api')->getJson('/api/service-order')->assertOk();

        $this->assertStringNotContainsString($order['callback_token'], $response->getContent());
    }
}
