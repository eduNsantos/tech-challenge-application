<?php

namespace App\Domain\ServiceOrderService\Interfaces;

use App\Application\ServiceOrderService\DTOs\CreateServiceOrderServiceDTO;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService;

interface ServiceOrderServiceInterface
{
    public function createServiceOrderService(CreateServiceOrderServiceDTO $dto): ServiceOrderService;

    public function startService(string $id): ServiceOrderService;

    public function finishService(string $id): ServiceOrderService;
}
