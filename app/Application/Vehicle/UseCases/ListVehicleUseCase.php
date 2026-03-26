<?php

namespace App\Application\Vehicle\UseCases;

use App\Application\Vehicle\DTOs\ListVehicleDTO;
use App\Domain\Vehicle\Interfaces\VehicleRepositoryInterface;

class ListVehicleUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $repository
    ) {}

    public function execute(ListVehicleDTO $dto): array
    {
        if ($dto->page !== null) {
            $perPage = $dto->perPage ?? 10;

            if ($perPage <= 0) {
                $perPage = 10;
            }

            return $this->repository->paginate($dto->page, $perPage);
        }

        return $this->repository->findAll();
    }
}
