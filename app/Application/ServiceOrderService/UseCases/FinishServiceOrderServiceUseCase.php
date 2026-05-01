<?php

namespace App\Application\ServiceOrderService\UseCases;

use App\Application\ServiceOrderService\DTOs\FinishServiceOrderServiceDTO;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService as ServiceOrderServiceEntity;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;

class FinishServiceOrderServiceUseCase
{
    public function __construct(private ServiceOrderServiceInterface $serviceOrderServiceRepository) {}

    public function execute(FinishServiceOrderServiceDTO $dto): ServiceOrderServiceEntity
    {
        return $this->serviceOrderServiceRepository->finishService(
            $dto->serviceOrderServiceId,
            $dto->finishedUserId
        );
    }
}
