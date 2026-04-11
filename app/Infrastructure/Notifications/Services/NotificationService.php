<?php

namespace App\Infrastructure\Notifications\Services;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Domain\Notification\Entity\Notification as NotificationEntity;
use App\Domain\Notification\ValueObjects\NotificationType;
use Illuminate\Support\Facades\Notification;
use App\Infrastructure\Notifications\GenericNotification;

class NotificationService implements NotificationServiceInterface
{
    public function send(NotificationEntity $notification): void
    {
        $channel = $this->mapTypeToChannel($notification->getType());
        $destination = $this->getDestination($notification);
        Notification::route($channel, $destination)
            ->notify(new GenericNotification($notification));
    }

    private function mapTypeToChannel(NotificationType $type): string
    {
        return match($type) {
            NotificationType::EMAIL => 'mail',
            NotificationType::SMS => 'vonage', // ou o driver que você usar
            NotificationType::PUSH => 'fcm',
        };
    }

    private function getDestination(NotificationEntity $notification): string
    {
        // Aqui você buscaria o e-mail/telefone do Customer baseado no recipientId
        return $notification->getRecipientId(); 
    }
}