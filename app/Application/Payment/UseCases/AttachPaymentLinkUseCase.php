<?php

namespace App\Application\Payment\UseCases;

use App\Application\Payment\DTOs\PaymentLinkNotificationDTO;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Events\ServiceOrderPaymentLinkReady;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Support\Observability\BusinessTelemetry;

/**
 * Recebe o link gerado pelo payment-service (Step ③) e dispara a notificação do orçamento ao cliente.
 */
class AttachPaymentLinkUseCase
{
    public function __construct(
        private ServiceOrderRepositoryInterface $serviceOrderRepository
    ) {}

    public function execute(PaymentLinkNotificationDTO $dto): ServiceOrder
    {
        $serviceOrder = $this->serviceOrderRepository->findByPaymentOrderId($dto->paymentOrderId);

        if (!$serviceOrder) {
            throw new \DomainException('Ordem de servico nao encontrada para o pagamento informado.');
        }

        $serviceOrder->attachPaymentLink($dto->paymentLink);
        $this->serviceOrderRepository->update($serviceOrder);

        event(new ServiceOrderPaymentLinkReady($serviceOrder));

        BusinessTelemetry::serviceOrder('service_order_payment_link_received', $serviceOrder, [
            'payment_order_id' => $serviceOrder->paymentOrderId,
        ]);

        return $serviceOrder;
    }
}
