<?php

namespace App\Application\ServiceOrder\UseCases;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Infrastructure\Messaging\ServiceOrderApprovalOutbox;
use Illuminate\Support\Facades\DB;

class RejectServiceOrderByTokenUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private ServiceOrderApprovalOutbox $approvalOutbox
    ) {}

    public function execute(string $token): ServiceOrder
    {
        return DB::transaction(function () use ($token): ServiceOrder {
            $serviceOrder = $this->serviceOrderRepository->findByApprovalTokenForUpdate($token);

            if (! $serviceOrder) {
                throw new \DomainException('Token de aprovacao invalido ou expirado.');
            }

            if ($serviceOrder->status !== ServiceOrder::STATUS_AGUARDANDO_APROVACAO) {
                throw new \DomainException('Esta ordem de servico nao esta aguardando aprovacao.');
            }

            $previousStatus = $serviceOrder->status;
            $serviceOrder->rejectQuote();
            $this->serviceOrderRepository->update($serviceOrder);
            $this->approvalOutbox->record($serviceOrder, $previousStatus);

            return $serviceOrder;
        });
    }
}
