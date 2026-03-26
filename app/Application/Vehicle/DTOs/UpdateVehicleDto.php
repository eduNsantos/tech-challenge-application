<?php

namespace App\Application\Vehicle\DTOs;

class UpdateVehicleDto
{
    public function __construct(
        public string $id,
        public ?string $brand,
        public ?string $model,
        public ?int $year,
        public ?string $plate
    ) {}
}
