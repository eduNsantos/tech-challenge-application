<?php

namespace App\Application\Notification\Handlers;

use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Domain\Notification\Entities\Notification as EntityNotification;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Domain\Notification\ValueObjects\NotificationStatus;
use App\Domain\Notification\ValueObjects\NotificationType;
use App\Domain\ServiceOrder\Events\ServiceOrderPaymentLinkReady;
use App\Support\Observability\BusinessTelemetry;
use Illuminate\Support\Str;

/**
 * Envia o orçamento ao cliente quando o payment-service devolve o link de pagamento.
 * A aprovação do orçamento acontece pelo pagamento; o cliente ainda pode recusar pelo link de recusa.
 */
class NotifyCustomerOnPaymentLinkReady
{
    public function __construct(
        private NotificationServiceInterface $notificationService,
        private NotificationRepositoryInterface $notificationRepository,
        private CustomerRepositoryInterface $customerRepository
    ) {}

    public function handle(ServiceOrderPaymentLinkReady $event): void
    {
        $serviceOrder = $event->serviceOrder;
        $customer = $this->customerRepository->findById($serviceOrder->customerId);

        if (!$customer) {
            return;
        }

        $rejectLink = url("/api/service-order/reject/{$serviceOrder->approvalToken}");
        $total = number_format($serviceOrder->totalBudget, 2, ',', '.');

        BusinessTelemetry::serviceOrder('service_order_quote_notification_started', $serviceOrder, [
            'customer_id' => $customer->id,
            'approval_token_present' => $serviceOrder->approvalToken !== null,
            'payment_link_present' => $serviceOrder->paymentLink !== null,
        ]);

        $notification = new EntityNotification(
            Str::uuid()->toString(),
            $customer->email,
            NotificationType::EMAIL,
            'Orçamento da sua ordem de serviço disponível',
            "Olá {$customer->name}, o orçamento da sua ordem de serviço foi gerado. Total: R$ {$total}.\n\n"
                . "Para aprovar o orçamento, realize o pagamento pelo link abaixo:\n{$serviceOrder->paymentLink}\n\n"
                . "Caso não queira prosseguir, recuse o orçamento:\n{$rejectLink}",
            NotificationStatus::PENDING,
            new \DateTimeImmutable()
        );

        $this->notificationRepository->save($notification);
        try {
            $this->notificationService->send($notification);
            $notification->markAsSent();
            BusinessTelemetry::integration('quote_sent_notification', true, [
                'service_order_id' => $serviceOrder->id,
                'customer_id' => $serviceOrder->customerId,
                'notification_type' => 'email',
            ]);
        } catch (\Throwable $th) {
            $notification->markAsFailed();
            BusinessTelemetry::integration('quote_sent_notification', false, [
                'service_order_id' => $serviceOrder->id,
                'customer_id' => $serviceOrder->customerId,
                'notification_type' => 'email',
                'error' => $th->getMessage(),
            ]);
        }
        $this->notificationRepository->save($notification);
    }
}
