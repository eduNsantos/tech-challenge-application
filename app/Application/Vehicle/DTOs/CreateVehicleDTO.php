<?php

namespace App\Application\Vehicle\DTOs;

class CreateVehicleDTO
{
    public function __construct(
        public string $brand,
        public string $model,
        public int $year,
        public string $plate
    ) {}
}
