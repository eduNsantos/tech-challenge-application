<?php

namespace App\Application\ServiceOrderService\UseCases;

use App\Application\ServiceOrderService\DTOs\StartServiceOrderServiceDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Domain\ServiceOrderService\Entities\ServiceOrderService as ServiceOrderServiceEntity;
use App\Domain\ServiceOrderService\Interfaces\ServiceOrderServiceInterface;

class StartServiceOrderServiceUseCase
{
    public function __construct(
        private ServiceOrderServiceInterface $serviceOrderServiceRepository,
        private ServiceOrderRepositoryInterface $serviceOrderRepository
    ) {}

    public function execute(StartServiceOrderServiceDTO $dto): ServiceOrderServiceEntity
    {
        $serviceOrderService = $this->serviceOrderServiceRepository->findById($dto->serviceOrderServiceId);

        if (!$serviceOrderService) {
            throw new \DomainException('Servico da OS nao encontrado.');
        }

        $serviceOrder = $this->serviceOrderRepository->findById($serviceOrderService->service_order_id);

        if (!$serviceOrder) {
            throw new \DomainException('Ordem de servico nao encontrada.');
        }

        if ($serviceOrder->status !== ServiceOrder::STATUS_EM_EXECUCAO) {
            throw new \DomainException('A OS deve estar em_execucao para iniciar o servico.');
        }

        return $this->serviceOrderServiceRepository->startService(
            $dto->serviceOrderServiceId,
            $dto->startedUserId
        );
    }
}
