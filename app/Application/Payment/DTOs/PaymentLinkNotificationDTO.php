<?php

namespace App\Application\Payment\DTOs;

class PaymentLinkNotificationDTO
{
    public function __construct(
        public string $paymentOrderId,
        public string $paymentLink
    ) {}
}
