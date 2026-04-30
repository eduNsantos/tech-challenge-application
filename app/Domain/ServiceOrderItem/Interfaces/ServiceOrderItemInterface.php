<?php

namespace App\Domain\ServiceOrderItem\Interfaces;

use App\Application\ServiceOrderItem\DTOs\CreateServiceOrderItemDto;
use App\Domain\ServiceOrderItem\Entities\ServiceOrderItem;

interface ServiceOrderItemInterface
{
    public function createServiceOrderItem(CreateServiceOrderItemDto $dto): ServiceOrderItem;
}
