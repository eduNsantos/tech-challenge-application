<?php

namespace App\Application\ServiceOrder\DTOs;

class RemoveServiceOrderItemDTO
{
    public function __construct(
        public string $id,
        public string $itemId
    ) {}
}
