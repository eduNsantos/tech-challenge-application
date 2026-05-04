<?php

namespace App\Application\Notification\UseCases;

use App\Application\Notification\DTOs\ShowNotificationDTO;
use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Notification\Entities\Notification;

class ShowNotificationUseCase
{
    public function __construct(
        private NotificationRepositoryInterface $repository
    ) {}

    public function execute(ShowNotificationDTO $dto): Notification
    {
        $notification = $this->repository->findById($dto->id);

        if (!$notification) {
            throw new \DomainException('Notificação não encontrada');
        }

        return $notification;
    }
}
