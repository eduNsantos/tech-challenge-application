<?php

namespace App\Presentation\Http\Controllers;

use App\Application\ServiceOrder\UseCases\ApproveServiceOrderByTokenUseCase;
use Illuminate\Http\JsonResponse;

class ServiceOrderApprovalController
{
    public function approve(string $token, ApproveServiceOrderByTokenUseCase $useCase): JsonResponse
    {
        $serviceOrder = $useCase->execute($token);

        return response()->json([
            'message' => 'Orçamento aprovado com sucesso. A ordem de serviço está em execução.',
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
            'quote_approved_at' => $serviceOrder->quoteApprovedAt,
        ]);
    }
}
