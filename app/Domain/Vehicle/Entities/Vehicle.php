<?php

namespace App\Domain\Vehicle\Entities;

use App\Domain\Vehicle\ValueObjects\Plate;

class Vehicle
{
    public function __construct(
        public string $id,
        public string $brand,
        public string $model,
        public int $year,
        public Plate $plate
    ) {}

    public static function create(
        string $brand,
        string $model,
        int $year,
        Plate $plate
    ): self {
        return new self(
            uuid_create(),
            $brand,
            $model,
            $year,
            $plate
        );
    }
}