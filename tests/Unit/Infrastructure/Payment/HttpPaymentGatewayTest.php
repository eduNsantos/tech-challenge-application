<?php

namespace Tests\Unit\Infrastructure\Payment;

use App\Domain\Payment\Exceptions\PaymentServiceUnavailableException;
use App\Infrastructure\Payment\HttpPaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpPaymentGatewayTest extends TestCase
{
    private array $order = ['order_id' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'amount_cents' => 550];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payment.url' => 'https://payment.example.com/']);
    }

    public function test_posts_order_to_payment_service(): void
    {
        Http::fake([
            'payment.example.com/orders' => Http::response(['status' => 'QUEUED', 'idempotency_key' => 'abc'], 202),
        ]);

        (new HttpPaymentGateway())->requestPaymentLink($this->order);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://payment.example.com/orders'
            && $request['order_id'] === $this->order['order_id']);
    }

    public function test_throws_when_payment_service_rejects(): void
    {
        Http::fake(['*' => Http::response(['message' => 'amount_cents invalido'], 400)]);

        $this->expectException(PaymentServiceUnavailableException::class);
        $this->expectExceptionMessage('amount_cents invalido');

        (new HttpPaymentGateway())->requestPaymentLink($this->order);
    }

    public function test_throws_when_payment_service_is_unreachable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->expectException(PaymentServiceUnavailableException::class);

        (new HttpPaymentGateway())->requestPaymentLink($this->order);
    }

    public function test_throws_when_not_configured(): void
    {
        config(['services.payment.url' => null]);
        Http::fake();

        $this->expectException(PaymentServiceUnavailableException::class);

        try {
            (new HttpPaymentGateway())->requestPaymentLink($this->order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_exception_maps_to_bad_gateway(): void
    {
        $this->assertSame(502, (new PaymentServiceUnavailableException('x'))->getStatusCode());
    }
}
