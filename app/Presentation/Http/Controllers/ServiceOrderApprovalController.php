<?php

namespace App\Presentation\Http\Controllers;

use App\Application\ServiceOrder\UseCases\ApproveServiceOrderByTokenUseCase;
use App\Application\ServiceOrder\UseCases\RejectServiceOrderByTokenUseCase;
use App\Presentation\Http\Requests\HandleServiceOrderApprovalRequest;
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
        ApproveServiceOrderByTokenUseCase $approveUseCase,
        RejectServiceOrderByTokenUseCase $rejectUseCase
    ): JsonResponse {
        $decision = $request->input('decision');

        if ($decision === 'approve') {
            $serviceOrder = $approveUseCase->execute($token);

            return response()->json([
                'message' => 'Aprovacao processada com sucesso.',
                'service_order_id' => $serviceOrder->id,
                'status' => $serviceOrder->status,
                'quote_approved_at' => $serviceOrder->quoteApprovedAt,
            ]);
        }

        $serviceOrder = $rejectUseCase->execute($token);

        return response()->json([
            'message' => 'Recusa processada com sucesso.',
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
        ]);
    }
}
