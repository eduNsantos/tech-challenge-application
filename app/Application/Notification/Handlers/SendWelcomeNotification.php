<?php

namespace App\Application\Notification\Handlers;

use App\Domain\Customer\Events\CustomerCreated;
use App\Domain\Notification\Entities\Notification as EntityNotification;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Domain\Notification\ValueObjects\NotificationStatus;
use App\Domain\Notification\ValueObjects\NotificationType;
use App\Infrastructure\Notifications\GenericNotification;
use App\Infrastructure\Notifications\WelcomeNotification;
use App\Support\Observability\BusinessTelemetry;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendWelcomeNotification
{
    public function __construct(
        private NotificationServiceInterface $notificationService,
        private NotificationRepositoryInterface $notificationRepository
    ) {}
    public function handle(CustomerCreated $event): void
    {
        $notification = new EntityNotification(
            Str::uuid()->toString(),
            $event->customer->id,
            NotificationType::EMAIL,
            'Bem-vindo ao nosso sistema ' . $event->customer->name,
            'Ficamos felizes em ter você conosco!',
            NotificationStatus::PENDING,
            new \DateTimeImmutable()
        );
        
        $this->notificationRepository->save($notification);
        try {
            $this->notificationService->send($notification);
            $notification->markAsSent();
            BusinessTelemetry::integration('welcome_notification', true, [
                'customer_id' => $event->customer->id,
                'notification_type' => 'email',
            ]);
        } catch (\Throwable $th) {
            $notification->markAsFailed();
            BusinessTelemetry::integration('welcome_notification', false, [
                'customer_id' => $event->customer->id,
                'notification_type' => 'email',
                'error' => $th->getMessage(),
            ]);
        }
        $this->notificationRepository->save($notification);
    }
}