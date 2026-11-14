<?php

namespace Tests;

use App\Domain\Payment\Interfaces\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakePaymentGateway;

abstract class TestCase extends BaseTestCase
{
    protected FakePaymentGateway $paymentGateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum teste chama o payment-service real
        $this->paymentGateway = new FakePaymentGateway();
        $this->app->instance(PaymentGatewayInterface::class, $this->paymentGateway);
    }
}
