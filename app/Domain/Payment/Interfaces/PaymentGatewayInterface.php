<?php

namespace App\Domain\Payment\Interfaces;

use App\Domain\Payment\Exceptions\PaymentServiceUnavailableException;

interface PaymentGatewayInterface
{
    /**
     * Solicita ao payment-service a abertura de uma cobrança (POST /orders).
     *
     * O link de pagamento é gerado de forma assíncrona e entregue depois em
     * POST /api/payment/notification; o resultado final chega no callback_url.
     *
     * @param array<string, mixed> $order Payload no formato do schema SQS do payment-service
     *
     * @throws PaymentServiceUnavailableException
     */
    public function requestPaymentLink(array $order): void;
}
