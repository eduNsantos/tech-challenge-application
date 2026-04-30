<?php

namespace App\Application\ServiceOrderItem\DTOs;

class CreateServiceOrderItemDTO
{
    public function __construct(
        public string $service_order_id,
        public string $item_id,
        public int $quantity,
        public float $price
    ) {}
}