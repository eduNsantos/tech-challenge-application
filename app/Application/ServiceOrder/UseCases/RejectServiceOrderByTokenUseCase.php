<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;

class RejectServiceOrderByTokenUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository
    ) {}

    public function execute(string $token): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findByApprovalToken($token);

        if (!$serviceOrder) {
            throw new \DomainException('Token de aprovacao invalido ou expirado.');
        }

        if ($serviceOrder->status !== ServiceOrder::STATUS_AGUARDANDO_APROVACAO) {
            throw new \DomainException('Esta ordem de servico nao esta aguardando aprovacao.');
        }

        $serviceOrder->rejectQuote();
        $this->serviceOrderRepository->update($serviceOrder);

        return $serviceOrder;
    }
}
