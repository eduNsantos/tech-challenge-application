<?php

namespace App\Application\ServiceOrder\DTOs;

class CreateServiceOrderDTO
{
    public function __construct(
        public string $vehicleId,
        public string $customerId,
        public array $services,
        public array $items = [],
        public bool $sendQuote = true
    ) {}
}
