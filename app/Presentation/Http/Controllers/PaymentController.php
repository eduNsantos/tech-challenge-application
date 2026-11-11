<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Payment\DTOs\PaymentLinkNotificationDTO;
use App\Application\Payment\DTOs\PaymentResultDTO;
use App\Application\Payment\UseCases\AttachPaymentLinkUseCase;
use App\Application\Payment\UseCases\HandlePaymentResultUseCase;
use App\Presentation\Http\Requests\PaymentCallbackRequest;
use App\Presentation\Http\Requests\PaymentLinkNotificationRequest;
use Illuminate\Http\JsonResponse;

class PaymentController
{
    public function notification(PaymentLinkNotificationRequest $request, AttachPaymentLinkUseCase $useCase): JsonResponse
    {
        $serviceOrder = $useCase->execute(new PaymentLinkNotificationDTO(
            paymentOrderId: $request->input('order_id'),
            paymentLink: $request->input('payment_link'),
        ));

        return response()->json([
            'message' => 'Link de pagamento registrado. Orcamento enviado ao cliente.',
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
        ]);
    }

    public function callback(string $token, PaymentCallbackRequest $request, HandlePaymentResultUseCase $useCase): JsonResponse
    {
        $dto = new PaymentResultDTO(
            callbackToken: $token,
            paymentOrderId: $request->input('order_id'),
            status: (string) $request->input('status'),
            mpPaymentId: $request->filled('mp_payment_id') ? (string) $request->input('mp_payment_id') : null,
        );

        $serviceOrder = $useCase->execute($dto);

        return response()->json([
            'message' => $dto->isPaid()
                ? 'Pagamento confirmado. Orcamento aprovado e ordem de servico em execucao.'
                : 'Pagamento nao concluido. Orcamento recusado e ordem de servico retornou para diagnostico.',
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
            'quote_approved_at' => $serviceOrder->quoteApprovedAt,
        ]);
    }
}
