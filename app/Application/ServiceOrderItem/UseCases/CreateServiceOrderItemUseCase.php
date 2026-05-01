<?php

namespace App\Application\ServiceOrderItem\UseCases;

use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Domain\ServiceOrderItem\Entities\ServiceOrderItem;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderItemInterface;

class CreateServiceOrderItemUseCase
{

    public function __construct(private ServiceOrderItemInterface $serviceOrderRepository) {
        $this->serviceOrderRepository = $serviceOrderRepository;
    }

    public function execute(CreateServiceOrderItemDTO $dto): ServiceOrderItem
    {
        return $this->serviceOrderRepository->createServiceOrderItem($dto);
    }
}
