<?php

namespace App\Application\Notification\Handlers;

use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
use App\Domain\Notification\Entities\Notification as EntityNotification;
use App\Domain\Notification\ValueObjects\NotificationStatus;
use App\Domain\Notification\ValueObjects\NotificationType;
use App\Support\Observability\BusinessTelemetry;
use Illuminate\Support\Str;
use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
class SendServiceOrderStatusNotification
{
    public function __construct(
        private NotificationServiceInterface $notificationService,
        private NotificationRepositoryInterface $notificationRepository
    ) {}
    public function handle(ServiceOrderStatusChanged $event)
    {
        $serviceOrder = $event->serviceOrder;
        $oldStatus = $event->oldStatus;
        $newStatus = $event->serviceOrder->status;

        if (!in_array($newStatus, [
            \App\Domain\ServiceOrder\Entities\ServiceOrder::STATUS_FINALIZADA,
            \App\Domain\ServiceOrder\Entities\ServiceOrder::STATUS_ENTREGUE,
        ], true)) {
            return;
        }

        BusinessTelemetry::serviceOrder('service_order_status_notification_started', $serviceOrder, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $notification = new EntityNotification(
            Str::uuid()->toString(),
            $serviceOrder->customerId,
            NotificationType::EMAIL,
            'Status da ordem de servico alterado',
            'O status da ordem de servico foi alterado para ' . $newStatus,
            NotificationStatus::PENDING,
            new \DateTimeImmutable()
        );
        $this->notificationRepository->save($notification);
        try {
            $this->notificationService->send($notification);
            $notification->markAsSent();
            BusinessTelemetry::integration('service_order_status_notification', true, [
                'service_order_id' => $serviceOrder->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'notification_type' => 'email',
            ]);
        } catch (\Throwable $th) {
            $notification->markAsFailed();
            BusinessTelemetry::integration('service_order_status_notification', false, [
                'service_order_id' => $serviceOrder->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'notification_type' => 'email',
                'error' => $th->getMessage(),
            ]);
        }
        $this->notificationRepository->save($notification);
    }
}