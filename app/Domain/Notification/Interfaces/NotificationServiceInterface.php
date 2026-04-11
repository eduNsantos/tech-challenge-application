<?php

namespace App\Domain\Notification\Interfaces;

use App\Domain\Notification\Entity\Notification;

interface NotificationServiceInterface
{
    public function send(Notification $notification): void;
}