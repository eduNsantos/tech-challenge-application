<?php

namespace App\Application\Vehicle\DTOs;

use App\Domain\Vehicle\ValueObjects\Plate;

class UpdateVehicleDto
{
    public function __construct(
        public string $id,
        public ?string $brand,
        public ?string $model,
        public ?int $year,
        public ?Plate $plate
    ) {}
}
