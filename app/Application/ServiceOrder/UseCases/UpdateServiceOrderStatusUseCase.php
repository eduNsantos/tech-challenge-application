<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
class UpdateServiceOrderStatusUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $repository
    ) {}

    public function execute(UpdateServiceOrderStatusDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->repository->findById($dto->id);

        if (!$serviceOrder) {
            throw new \Exception('Ordem de servico nao encontrada');
        }
        $oldStatus = $serviceOrder->status;
        $serviceOrder->changeStatus($dto->status);
        $this->repository->update($serviceOrder);
        event(new ServiceOrderStatusChanged($serviceOrder, $oldStatus));
        return $serviceOrder;
    }
}
