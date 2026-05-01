<?php

namespace App\Domain\ServiceOrderItem\Interfaces;

use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDTO;
use App\Domain\ServiceOrderItem\Entities\ServiceOrderItem;

interface ServiceOrderItemInterface
{
    public function createServiceOrderItem(CreateServiceOrderItemDTO $dto): ServiceOrderItem;
}
