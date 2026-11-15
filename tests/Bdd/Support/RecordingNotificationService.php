<?php

namespace Tests\Bdd\Support;

use App\Domain\Notification\Entities\Notification;
use App\Domain\Notification\Interfaces\NotificationServiceInterface;

/**
 * Substitui o envio real de e-mail nos cenários BDD. As notificações continuam
 * gravadas na tabela notifications pelos handlers; aqui só evitamos o envio.
 */
final class RecordingNotificationService implements NotificationServiceInterface
{
    /** @var array<int, Notification> */
    public array $sent = [];

    public function send(Notification $notification): void
    {
        $this->sent[] = $notification;
    }
}
