<?php

namespace App\Domain\Notification\Interfaces;

use App\Domain\Notification\Entity\Notification;

interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;
    public function findById(string $id): ?Notification;
}