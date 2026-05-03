<?php

namespace App\Application\ServiceOrderService\UseCases;

use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;

class GetAverageServiceExecutionTimeUseCase
{
    public function __construct(private ServiceOrderServiceInterface $serviceOrderServiceRepository) {}

    public function execute(): array
    {
        return $this->serviceOrderServiceRepository->averageExecutionTimeByService();
    }
}
