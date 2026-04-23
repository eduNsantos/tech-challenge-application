<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Notification\Interfaces\NotificationRepositoryInterface;
use App\Domain\Notification\Entities\Notification as NotificationEntity;
use App\Infrastructure\Persistence\Eloquent\Models\NotificationModel;

class NotificationRepositoryEloquent implements NotificationRepositoryInterface
{
    public function save(NotificationEntity $notification): void
    {
        NotificationModel::updateOrCreate(
            ['id' => $notification->getId()],
            [
                'recipient_id' => $notification->getRecipientId(),
                'type'         => $notification->getType()->value,
                'subject'      => $notification->getSubject(),
                'content'      => $notification->getContent(),
                'status'       => $notification->getStatus()->value,
                'sent_at'      => $notification->getSentAt()?->format('Y-m-d H:i:s'),
            ]
        );
    }
    public function findById(string $id): ?NotificationEntity
    {
        $model = NotificationModel::find($id);
        if (!$model) return null;
        return new NotificationEntity(
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