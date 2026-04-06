<?php

namespace App\Application\Vehicle\DTOs;

class CreateVehicleDTO
{
    public function __construct(
        public string $name,
        public string $price
    ) {}
}
