<?php

namespace Tests\Bdd\Context;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Domain\Payment\Exceptions\PaymentServiceUnavailableException;
use App\Lambda\ServiceOrderExecutionHandler;
use App\Models\User;
use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Bref\Context\Context as LambdaContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Tests\Bdd\Support\LaravelHarness;
use Tests\Bdd\Support\RecordingNotificationService;

/**
 * Passos do fluxo completo da O.S. (Saga) do ponto de vista do application.
 *
 * Cada cenário sobe uma aplicação Laravel nova com banco SQLite em memória.
 * O payment-service é o FakePaymentGateway (registra os pedidos de cobrança) e as
 * respostas dele chegam pelos mesmos endpoints usados em produção (/api/payment/...).
 * O services-ms é simulado lendo a tabela outbox (o que seria publicado no SQS) e
 * enviando eventos pela Lambda ServiceOrderExecutionHandler, como a fila FIFO faria.
 */
final class ServiceOrderSagaContext implements Context
{
    private const CUSTOMER_EMAIL = 'cliente.bdd@example.com';
    private const MECHANIC_EMAIL = 'mecanico.bdd@example.com';

    private LaravelHarness $harness;
    private User $attendant;
    private User $mechanic;
    private string $customerId;
    private string $vehicleId;

    /** @var array<string, string> nome do serviço → id */
    private array $services = [];

    /** @var array<string, string> nome da peça → id */
    private array $parts = [];

    private ?string $serviceOrderId = null;
    private ?TestResponse $lastResponse = null;
    private ?array $lastExecutionEvent = null;
    private ?array $lastPaymentResult = null;
    private ?int $previousStatusSequence = null;

    #[BeforeSuite]
    public static function prepareEnvironment(): void
    {
        LaravelHarness::configureEnvironment();
    }

    #[BeforeScenario]
    public function bootApplication(): void
    {
        $this->harness = new LaravelHarness('bdd');
        $this->harness->boot();
        $this->harness->application()->instance(
            NotificationServiceInterface::class,
            new RecordingNotificationService()
        );
    }

    #[AfterScenario]
    public function shutdownApplication(): void
    {
        $this->harness->shutdown();
    }

    // ── Contexto ────────────────────────────────────────────────────────────

    #[Given('que a oficina tem um atendente e um mecânico cadastrados')]
    public function workshopStaff(): void
    {
        $this->attendant = User::factory()->create([
            'email' => 'atendente.bdd@example.com',
            'document' => '52998224725',
            'role' => 'atendente',
        ]);

        $this->mechanic = User::factory()->create([
            'email' => self::MECHANIC_EMAIL,
            'document' => '11144477735',
            'role' => 'mecanico',
        ]);
    }

    #[Given('o cliente :name com CPF :document')]
    public function customer(string $name, string $document): void
    {
        $this->customerId = Str::uuid()->toString();

        DB::table('customers')->insert([
            'id' => $this->customerId,
            'name' => $name,
            'email' => self::CUSTOMER_EMAIL,
            'phone' => '11999990000',
            'document' => $document,
            'created_user_id' => $this->attendant->id,
            'updated_user_id' => $this->attendant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Given('o veículo de placa :plate')]
    public function vehicle(string $plate): void
    {
        $this->vehicleId = (string) $this->as($this->attendant)
            ->postJson('/api/vehicle', ['brand' => 'Toyota', 'model' => 'Corolla', 'year' => 2020, 'plate' => $plate])
            ->assertSuccessful()
            ->json('id');
    }

    #[Given('o serviço :name custando R$ :price')]
    public function service(string $name, string $price): void
    {
        $this->services[$name] = (string) $this->as($this->attendant)
            ->postJson('/api/service', ['name' => $name, 'price' => $this->money($price)])
            ->assertSuccessful()
            ->json('id');
    }

    #[Given('a peça :name custando R$ :price com :quantity unidades em estoque')]
    public function part(string $name, string $price, int $quantity): void
    {
        $id = (string) $this->as($this->attendant)
            ->postJson('/api/item', [
                'name' => $name,
                'code' => 'BDD-'.(count($this->parts) + 1),
                'type' => 'part',
                'measure_unit' => 'un',
                'minimum_quantity' => 1,
                'unit_price' => $this->money($price),
            ])
            ->assertSuccessful()
            ->json('id');

        $this->as($this->attendant)
            ->postJson("/api/item/{$id}/stock/entry", ['quantity' => $quantity, 'reason' => 'Estoque inicial BDD'])
            ->assertSuccessful();

        $this->parts[$name] = $id;
    }

    #[Given('a integração com o services-ms está habilitada')]
    public function servicesMsEnabled(): void
    {
        config(['services_ms.enabled' => true]);
    }

    // ── Atendente e mecânico (API do application) ──────────────────────────

    #[When('o atendente abre uma O.S. com :serviceQty :service e :partQty :part')]
    public function openServiceOrder(int $serviceQty, string $service, int $partQty, string $part): void
    {
        $this->lastResponse = $this->createServiceOrder($serviceQty, $service, $partQty, $part, sendQuote: false)
            ->assertCreated();
        $this->serviceOrderId = $this->lastResponse->json('service_order.id');
    }

    #[When('o atendente abre uma O.S. com :serviceQty :service e :partQty :part já enviando o orçamento')]
    public function openServiceOrderSendingQuote(int $serviceQty, string $service, int $partQty, string $part): void
    {
        $this->lastResponse = $this->createServiceOrder($serviceQty, $service, $partQty, $part, sendQuote: true);
        $this->serviceOrderId = $this->lastResponse->json('service_order.id');
    }

    #[When('o mecânico envia o orçamento para aprovação')]
    public function sendQuote(): void
    {
        $this->lastResponse = $this->as($this->mechanic)
            ->putJson("/api/service-order/{$this->serviceOrderId}", ['send_quote' => true])
            ->assertOk();
    }

    #[When('o atendente registra a entrega do veículo')]
    public function deliverVehicle(): void
    {
        $this->changeStatus('entregue');
    }

    #[Given('o atendente volta a O.S. para :status')]
    public function changeStatusTo(string $status): void
    {
        $this->changeStatus($status);
    }

    // ── payment-service ─────────────────────────────────────────────────────

    #[Given('que o payment-service está indisponível')]
    public function paymentServiceUnavailable(): void
    {
        $this->harness->paymentGateway()->failWith =
            new PaymentServiceUnavailableException('payment-service indisponivel');
    }

    #[When('o payment-service devolve o link de pagamento')]
    public function paymentLinkReady(): void
    {
        $order = $this->lastPaymentRequest();

        $this->lastResponse = $this->asGuest()->postJson('/api/payment/notification', [
            'order_id' => $order['order_id'],
            'payment_link' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=bdd',
            'status' => 'WAITING_PAYMENT',
        ])->assertOk();
    }

    #[When('o payment-service informa o resultado :status do pagamento')]
    public function paymentResult(string $status): void
    {
        $order = $this->lastPaymentRequest();

        $this->lastPaymentResult = [
            'path' => parse_url($order['callback_url'], PHP_URL_PATH),
            'body' => ['order_id' => $order['order_id'], 'status' => $status, 'mp_payment_id' => '999000111'],
        ];

        $this->lastResponse = $this->asGuest()
            ->postJson($this->lastPaymentResult['path'], $this->lastPaymentResult['body'])
            ->assertOk();
    }

    #[When('o payment-service reenvia o mesmo resultado :status')]
    public function paymentResultAgain(string $status): void
    {
        Assert::assertSame($status, $this->lastPaymentResult['body']['status'] ?? null);

        $this->lastResponse = $this->asGuest()
            ->postJson($this->lastPaymentResult['path'], $this->lastPaymentResult['body']);
    }

    #[Then('o payment-service deve receber uma cobrança de :amount centavos')]
    public function paymentRequested(int $amount): void
    {
        $order = $this->lastPaymentRequest();

        Assert::assertSame($amount, $order['amount_cents']);
        Assert::assertSame('BRL', $order['currency']);
        Assert::assertSame($this->serviceOrderId, $order['metadata']['service_order_id']);
        Assert::assertStringContainsString('/api/payment/callback/', $order['callback_url']);
        Assert::assertStringEndsWith('/api/payment/notification', $order['notification_url']);
    }

    #[Then('o payment-service deve ter recebido :count cobranças com identificadores diferentes')]
    public function paymentRequestsWithDistinctIds(int $count): void
    {
        $ids = array_column($this->harness->paymentGateway()->requests, 'order_id');

        Assert::assertCount($count, $ids);
        Assert::assertCount($count, array_unique($ids), 'Cada envio de orçamento deve abrir uma nova cobrança.');
    }

    #[Then('nenhuma cobrança deve ter sido enviada ao payment-service')]
    public function noPaymentRequested(): void
    {
        Assert::assertSame([], $this->harness->paymentGateway()->requests);
    }

    // ── services-ms ─────────────────────────────────────────────────────────

    #[Then('o services-ms deve receber o evento :type')]
    public function eventPublished(string $type): void
    {
        Assert::assertNotNull($this->latestOutboxEvent($type), "Evento {$type} não foi gravado na outbox.");
    }

    #[Then('o services-ms deve receber o evento :type com status :status')]
    public function eventPublishedWithStatus(string $type, string $status): void
    {
        $event = $this->latestOutboxEvent($type);

        Assert::assertNotNull($event, "Evento {$type} não foi gravado na outbox.");
        Assert::assertSame($status, $event['status']);
        Assert::assertSame($this->serviceOrderId, $event['service_order_id']);
    }

    #[Then('nenhum evento deve ter sido publicado para o services-ms')]
    public function noEventPublished(): void
    {
        Assert::assertSame(0, DB::table('outbox')->count());
    }

    #[When('o services-ms informa o início da execução')]
    public function executionStarted(): void
    {
        $this->sendExecutionEvent('ServiceOrderExecutionStarted', $this->currentStatusSequence());
    }

    #[When('o services-ms informa o fim da execução')]
    public function executionFinished(): void
    {
        $this->sendExecutionEvent('ServiceOrderExecutionFinished', $this->currentStatusSequence());
    }

    #[When('o services-ms informa o fim da execução com a sequência anterior')]
    public function executionFinishedWithStaleSequence(): void
    {
        Assert::assertNotNull($this->previousStatusSequence);
        $this->sendExecutionEvent('ServiceOrderExecutionFinished', $this->previousStatusSequence);
    }

    #[When('o services-ms reenvia a mesma mensagem de fim da execução')]
    public function executionFinishedAgain(): void
    {
        Assert::assertNotNull($this->lastExecutionEvent);
        $this->deliverToExecutionQueue($this->lastExecutionEvent);
    }

    // ── Pré-condições compostas ─────────────────────────────────────────────

    #[Given('uma O.S. aguardando pagamento com :serviceQty :service e :partQty :part')]
    public function serviceOrderAwaitingPayment(int $serviceQty, string $service, int $partQty, string $part): void
    {
        $this->openServiceOrder($serviceQty, $service, $partQty, $part);
        $this->sendQuote();
        $this->paymentLinkReady();
        $this->assertStatus('aguardando_aprovacao');
    }

    #[Given('uma O.S. em execução com :serviceQty :service e :partQty :part')]
    public function serviceOrderInExecution(int $serviceQty, string $service, int $partQty, string $part): void
    {
        $this->serviceOrderAwaitingPayment($serviceQty, $service, $partQty, $part);
        $this->paymentResult('PAID');
        $this->assertStatus('em_execucao');
    }

    // ── Verificações ────────────────────────────────────────────────────────

    #[Then('a O.S. deve estar com status :status')]
    public function assertStatus(string $status): void
    {
        $this->as($this->attendant)
            ->getJson("/api/service-order/{$this->serviceOrderId}")
            ->assertOk()
            ->assertJsonPath('service_order.status', $status);
    }

    #[Then('o estoque da peça :part deve ser :quantity')]
    public function assertStock(string $part, int $quantity): void
    {
        $stock = $this->as($this->attendant)
            ->getJson("/api/item/{$this->parts[$part]}")
            ->assertOk()
            ->json('stock_quantity');

        Assert::assertEquals($quantity, (float) $stock);
    }

    #[Then('nenhuma baixa de estoque deve ter sido registrada')]
    public function noStockWithdrawal(): void
    {
        $this->harness->dontSeeInDatabase('stock_movements', [
            'service_order_id' => $this->serviceOrderId,
            'movement_type' => 'withdrawal',
        ]);
    }

    #[Then('o cliente deve ser notificado com o assunto :subject')]
    public function customerNotified(string $subject): void
    {
        $this->harness->seeInDatabase('notifications', [
            'recipient_id' => self::CUSTOMER_EMAIL,
            'subject' => $subject,
        ]);
    }

    #[Then('o cliente deve ter recebido :count notificação com o assunto :subject')]
    public function customerNotifiedTimes(int $count, string $subject): void
    {
        Assert::assertSame($count, DB::table('notifications')
            ->where('recipient_id', self::CUSTOMER_EMAIL)
            ->where('subject', $subject)
            ->count());
    }

    #[Then('o mecânico deve ser notificado com o assunto :subject')]
    public function mechanicNotified(string $subject): void
    {
        $this->harness->seeInDatabase('notifications', [
            'recipient_id' => self::MECHANIC_EMAIL,
            'subject' => $subject,
        ]);
    }

    #[Then('a requisição deve falhar com status :status')]
    public function requestFailed(int $status): void
    {
        $this->lastResponse->assertStatus($status);
    }

    #[Then('a resposta deve conter a mensagem :message')]
    public function responseMessage(string $message): void
    {
        Assert::assertStringContainsString($message, (string) $this->lastResponse->json('message'));
    }

    #[Then('nenhuma ordem de serviço deve ter sido gravada')]
    public function noServiceOrder(): void
    {
        Assert::assertSame(0, DB::table('service_orders')->count());
    }

    // ── Auxiliares ──────────────────────────────────────────────────────────

    private function createServiceOrder(int $serviceQty, string $service, int $partQty, string $part, bool $sendQuote): TestResponse
    {
        return $this->as($this->attendant)->postJson('/api/service-order', [
            'customer_id' => $this->customerId,
            'vehicle_id' => $this->vehicleId,
            'services' => [['service_id' => $this->services[$service], 'quantity' => $serviceQty]],
            'items' => [['item_id' => $this->parts[$part], 'quantity' => $partQty]],
            'send_quote' => $sendQuote,
        ]);
    }

    private function changeStatus(string $status): void
    {
        $this->previousStatusSequence = $this->currentStatusSequence();

        $this->lastResponse = $this->as($this->attendant)
            ->patchJson("/api/service-order/{$this->serviceOrderId}/status", ['status' => $status])
            ->assertOk();
    }

    /** Sequência do último status publicado — é o valor que o services-ms devolve em status_sequence. */
    private function currentStatusSequence(): int
    {
        return (int) DB::table('outbox')
            ->where('group_id', $this->serviceOrderId)
            ->whereIn('type', ['ServiceOrderCreated', 'ServiceOrderStatusChanged'])
            ->max('id');
    }

    private function sendExecutionEvent(string $type, int $sequence): void
    {
        $this->lastExecutionEvent = [
            'event_id' => Str::uuid()->toString(),
            'type' => $type,
            'version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'service_order_id' => $this->serviceOrderId,
            'status_sequence' => $sequence,
        ];

        $this->deliverToExecutionQueue($this->lastExecutionEvent);
    }

    /** Entrega a mensagem como o event source mapping da fila FIFO faria. */
    private function deliverToExecutionQueue(array $event): void
    {
        $result = app(ServiceOrderExecutionHandler::class)->handle(
            ['Records' => [[
                'messageId' => Str::uuid()->toString(),
                'receiptHandle' => 'bdd',
                'body' => json_encode($event, JSON_THROW_ON_ERROR),
                'attributes' => ['ApproximateReceiveCount' => '1', 'MessageGroupId' => $event['service_order_id']],
                'messageAttributes' => [],
                'eventSource' => 'aws:sqs',
            ]]],
            new LambdaContext('bdd', (int) (microtime(true) * 1000) + 30_000, 'arn:bdd', '')
        );

        Assert::assertNull($result, 'A Lambda devolveu a mensagem para a fila: '.json_encode($result));
    }

    /** @return array<string, mixed>|null payload do último evento do tipo para a O.S. atual */
    private function latestOutboxEvent(string $type): ?array
    {
        $payload = DB::table('outbox')
            ->where('group_id', $this->serviceOrderId)
            ->where('type', $type)
            ->orderByDesc('id')
            ->value('payload');

        return $payload ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /** @return array<string, mixed> */
    private function lastPaymentRequest(): array
    {
        $order = $this->harness->paymentGateway()->lastRequest();
        Assert::assertNotNull($order, 'Nenhuma cobrança foi enviada ao payment-service.');

        return $order;
    }

    private function as(User $user): LaravelHarness
    {
        $this->harness->logout();

        return $this->harness->actingAs($user, 'api');
    }

    private function asGuest(): LaravelHarness
    {
        $this->harness->logout();

        return $this->harness;
    }

    private function money(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
