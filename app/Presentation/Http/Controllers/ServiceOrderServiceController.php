<?php

namespace App\Presentation\Http\Controllers;

use App\Application\ServiceOrderService\DTOs\FinishServiceOrderServiceDTO;
use App\Application\ServiceOrderService\DTOs\StartServiceOrderServiceDTO;
use App\Application\ServiceOrderService\UseCases\FinishServiceOrderServiceUseCase;
use App\Application\ServiceOrderService\UseCases\StartServiceOrderServiceUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOrderServiceController
{
    public function start(
        string $id,
        Request $request,
        StartServiceOrderServiceUseCase $useCase
    ): JsonResponse {
        $dto = new StartServiceOrderServiceDTO(
            serviceOrderServiceId: $id,
            startedUserId: $request->user()?->id
        );

        $serviceOrderService = $useCase->execute($dto);

        return response()->json([
            'service_order_service' => $this->present($serviceOrderService),
            'message' => 'Servico iniciado com sucesso',
        ]);
    }

    public function finish(
        string $id,
        Request $request,
        FinishServiceOrderServiceUseCase $useCase
    ): JsonResponse {
        $dto = new FinishServiceOrderServiceDTO(
            serviceOrderServiceId: $id,
            finishedUserId: $request->user()?->id
        );

        $serviceOrderService = $useCase->execute($dto);

        return response()->json([
            'service_order_service' => $this->present($serviceOrderService),
            'message' => 'Servico finalizado com sucesso',
        ]);
    }

    private function present($serviceOrderService): array
    {
        return [
            'id'                => $serviceOrderService->id,
            'service_order_id'  => $serviceOrderService->service_order_id,
            'service_id'        => $serviceOrderService->service_id,
            'quantity'          => $serviceOrderService->quantity,
            'price'             => $serviceOrderService->price,
            'started_at'        => $serviceOrderService->started_at,
            'finished_at'       => $serviceOrderService->finished_at,
            'started_user_id'   => $serviceOrderService->started_user_id,
            'finished_user_id'  => $serviceOrderService->finished_user_id,
        ];
    }
}
