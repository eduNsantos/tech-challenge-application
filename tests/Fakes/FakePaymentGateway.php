<?php

namespace Tests\Fakes;

use App\Domain\Payment\Interfaces\PaymentGatewayInterface;

/**
 * Substitui o payment-service nos testes de Feature; registra os pedidos enviados.
 */
class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $requests = [];

    public ?\Throwable $failWith = null;

    public function requestPaymentLink(array $order): void
    {
        if ($this->failWith) {
            throw $this->failWith;
        }

        $this->requests[] = $order;
    }

    public function lastRequest(): ?array
    {
        return $this->requests[array_key_last($this->requests)] ?? null;
    }
}
