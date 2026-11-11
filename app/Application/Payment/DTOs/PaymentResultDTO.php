<?php

namespace App\Application\Payment\DTOs;

class PaymentResultDTO
{
    public const STATUS_PAID = 'PAID';

    public function __construct(
        public string $callbackToken,
        public string $paymentOrderId,
        public string $status,
        public ?string $mpPaymentId = null
    ) {}

    public function isPaid(): bool
    {
        return strtoupper($this->status) === self::STATUS_PAID;
    }
}
