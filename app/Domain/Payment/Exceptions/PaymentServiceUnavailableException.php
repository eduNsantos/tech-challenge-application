<?php

namespace App\Domain\Payment\Exceptions;

/**
 * Falha ao comunicar com o payment-service. O handler global usa getStatusCode() → 502.
 */
class PaymentServiceUnavailableException extends \RuntimeException
{
    public function getStatusCode(): int
    {
        return 502;
    }
}
