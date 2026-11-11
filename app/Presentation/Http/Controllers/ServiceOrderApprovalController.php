<?php

namespace App\Presentation\Http\Controllers;

use App\Application\ServiceOrder\UseCases\RejectServiceOrderByTokenUseCase;
use App\Presentation\Http\Requests\HandleServiceOrderApprovalRequest;
use Illuminate\Http\JsonResponse;

/**
 * Decisão do cliente sobre o orçamento. A aprovação acontece pelo pagamento
 * (PaymentController::callback); por aqui o cliente apenas recusa.
 */
class ServiceOrderApprovalController
{
    public function reject(string $token, RejectServiceOrderByTokenUseCase $useCase): JsonResponse
    {
        $serviceOrder = $useCase->execute($token);

        return response()->json([
            'message' => 'Orcamento recusado com sucesso. A ordem de servico retornou para diagnostico.',
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
        ]);
    }

    public function handle(
        string $token,
        HandleServiceOrderApprovalRequest $request,
        RejectServiceOrderByTokenUseCase $rejectUseCase
    ): JsonResponse {
        $serviceOrder = $rejectUseCase->execute($token);

        return response()->json([
            'message' => 'Recusa processada com sucesso.',
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
        ]);
    }
}
