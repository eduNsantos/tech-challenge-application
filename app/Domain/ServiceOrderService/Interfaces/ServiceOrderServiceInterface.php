<?php

namespace App\Domain\ServiceOrderService\Interfaces;

use App\Application\ServiceOrderService\DTOs\CreateServiceOrderServiceDTO;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService;

interface ServiceOrderServiceInterface
{
    public function createServiceOrderService(CreateServiceOrderServiceDTO $dto): ServiceOrderService;

    public function findById(string $id): ?ServiceOrderService;

    public function startService(string $id, ?int $startedUserId = null): ServiceOrderService;

    public function finishService(string $id, ?int $finishedUserId = null): ServiceOrderService;

    public function averageExecutionTimeByService(): array;
}
