<?php

namespace App\Application\ServiceOrder\DTOs;

class CreateServiceOrderDTO
{
    public function __construct(
        public string $vehicleId,
        public array $services,
        public array $parts = [],
        public bool $sendQuote = true
    ) {}
}
