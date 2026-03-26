<?php

namespace App\Domain\Vehicle\Repositories;

use App\Domain\Vehicle\Entities\Vehicle;
use App\Infrastructure\Persistence\Eloquent\Models\VehicleModel;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): void;
    /**
     * @return VehicleModel[]
     */
    public function findAll(): array;
    public function findByPlate(string $plate): ?Vehicle;
    public function paginate(int $page, int $perPage): array;
}
