<?php

namespace App\Application\ServiceOrderService\DTOs;

class CreateServiceOrderServiceDTO
{
    public function __construct(
        public string $service_order_id,
        public string $service_id,
        public int $quantity,
        public float $price,
        public ?string $started_at = null,
        public ?string $finished_at = null
    ) {}
}
