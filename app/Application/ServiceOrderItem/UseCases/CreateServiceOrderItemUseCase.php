<?php

namespace App\Application\ServiceOrderItem\UseCases;

use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Domain\ServiceOrderItem\Entities\ServiceOrderItem;
use App\Domain\ServiceOrderItem\Interfaces\ServiceOrderInterface;

class CreateServiceOrderItemUseCase
{

    public function __construct(private ServiceOrderInterface $serviceOrderRepository) {
        $this->serviceOrderRepository = $serviceOrderRepository;
    }

    public function execute(CreateServiceOrderItemDTO $dto): ServiceOrderItem
    {
        return $this->serviceOrderRepository->createServiceOrderItem($dto);
    }
}