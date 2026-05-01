<?php

namespace App\Domain\ServiceOrderService\Entities;

class ServiceOrderService
{
    public function __construct(
        public string $id,
        public string $service_order_id,
        public string $service_id,
        public int $quantity,
        public float $price,
        public ?string $started_at,
        public ?string $finished_at
    ) {}
}
