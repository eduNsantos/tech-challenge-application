<?php

namespace App\Application\ServiceOrder\DTOs;

class CreateServiceOrderDTO
{
    public function __construct(
        public string $vehicleBrand,
        public string $vehicleModel,
        public int $vehicleYear,
        public string $vehiclePlate,
        public array $services,
        public array $parts = [],
        public bool $sendQuote = true
    ) {}
}
