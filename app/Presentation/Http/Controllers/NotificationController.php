<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Notification\DTOs\ListNotificationDTO;
use App\Application\Notification\DTOs\ShowNotificationDTO;
use App\Application\Notification\UseCases\ListNotificationUseCase;
use App\Application\Notification\UseCases\ShowNotificationUseCase;
use App\Presentation\Http\Requests\ListNotificationRequest;

class NotificationController
{
    public function list(ListNotificationRequest $request, ListNotificationUseCase $useCase)
    {
        $dto = new ListNotificationDTO(
            page: $request->input('page', 1),
            perPage: $request->input('perPage', 10)
        );

        $notifications = $useCase->execute($dto);

        return response()->json($notifications);
    }

    public function show(string $id, ShowNotificationUseCase $useCase)
    {
        $dto = new ShowNotificationDTO(
            id: $id
        );

        $notification = $useCase->execute($dto);

        return response()->json($notification);
    }
}