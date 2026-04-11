<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\ListServiceOrderDTO;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;

class ListServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $repository
    ) {}

    public function execute(ListServiceOrderDTO $dto): array
    {
        $perPage = $dto->perPage;

        if ($perPage <= 0) {
            $perPage = 10;
        }

        return $this->repository->paginate($dto->page, $perPage);
    }
}
