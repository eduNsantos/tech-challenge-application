<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Notification\Entity\Notification;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;

class NotificationRepositoryEloquent implements NotificationRepositoryInterface
{
    public function save(Notification $notification): void
    {
        NotificationModel::create([
            'id' => $notification->getId(),
            'recipient_id' => $notification->getRecipientId(),
            'type' => $notification->getType()->value,
            'subject' => $notification->getSubject(),
            'content' => $notification->getContent(),
            'status' => $notification->getStatus(),
            'sent_at' => $notification->getSentAt(),
        ]);
    }
    public function findById(string $id): ?Notification
    {
        $model = NotificationModel::find($id);
        if (!$model) return null;
        return new Notification(
            $model->id,
            $model->recipient_id,
            $model->type,
            $model->subject,
            $model->content,
            $model->status,
            $model->sent_at
        );
    }
}