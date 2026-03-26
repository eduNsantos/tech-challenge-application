<?php

namespace App\Domain\Vehicle\Repositories;

use App\Domain\Vehicle\Entities\Vehicle;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): void;
    public function findByPlate(string $plate): ?Vehicle;
}
