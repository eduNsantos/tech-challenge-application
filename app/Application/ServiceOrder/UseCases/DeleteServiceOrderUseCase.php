<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\DeleteServiceOrderDTO;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;

class DeleteServiceOrderUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $repository
    ) {}

    public function execute(DeleteServiceOrderDTO $dto): void
    {
        $serviceOrder = $this->repository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \DomainException('Ordem de servico nao encontrada');
        }

        $this->repository->delete($dto->id);
    }
}
