<?php

namespace App\Domain\ServiceOrderItem\Entities;

class ServiceOrderItem
{
    public function __construct(
        public string $id,
        public string $service_order_id,
        public string $item_id,
        public int $quantity,
        public float $price
    ) {}
}
