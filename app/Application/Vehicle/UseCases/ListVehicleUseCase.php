<?php

namespace App\Application\Vehicle\UseCases;

use App\Domain\Vehicle\Repositories\VehicleRepositoryInterface;

class ListVehicleUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $repository
    ) {}

    public function execute()
    {
        return $this->repository->findAll();
    }
}
