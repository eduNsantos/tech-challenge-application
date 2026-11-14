<?php

namespace App\Application\Payment\UseCases;

use App\Application\Payment\DTOs\PaymentResultDTO;
use App\Application\ServiceOrder\UseCases\ApproveServiceOrderByTokenUseCase;
use App\Application\ServiceOrder\UseCases\RejectServiceOrderByTokenUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Support\Observability\BusinessTelemetry;

/**
 * Resultado final da cobrança vindo do payment-service (callback_url).
 * PAID aprova o orçamento; qualquer outro status (REJECTED, EXPIRED, ...) segue o fluxo de recusa.
 */
class HandlePaymentResultUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository,
        private ApproveServiceOrderByTokenUseCase $approveUseCase,
        private RejectServiceOrderByTokenUseCase $rejectUseCase
    ) {}

    public function execute(PaymentResultDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findByPaymentCallbackToken($dto->callbackToken);

        if (!$serviceOrder) {
            throw new \DomainException('Callback de pagamento invalido ou ja processado.');
        }

        if ($serviceOrder->paymentOrderId !== $dto->paymentOrderId) {
            throw new \DomainException('order_id nao corresponde a cobranca desta ordem de servico.');
        }

        if ($serviceOrder->approvalToken === null) {
            throw new \DomainException('Esta ordem de servico nao esta aguardando aprovacao.');
        }

        $result = $dto->isPaid()
            ? $this->approveUseCase->execute($serviceOrder->approvalToken)
            : $this->rejectUseCase->execute($serviceOrder->approvalToken);

        BusinessTelemetry::serviceOrder('service_order_payment_result_received', $result, [
            'payment_order_id' => $dto->paymentOrderId,
            'payment_status' => strtoupper($dto->status),
            'mp_payment_id' => $dto->mpPaymentId,
        ]);

        return $result;
    }
}
