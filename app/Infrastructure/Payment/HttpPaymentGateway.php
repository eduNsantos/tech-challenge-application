<?php

namespace App\Infrastructure\Payment;

use App\Domain\Payment\Exceptions\PaymentServiceUnavailableException;
use App\Domain\Payment\Interfaces\PaymentGatewayInterface;
use App\Support\Observability\BusinessTelemetry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpPaymentGateway implements PaymentGatewayInterface
{
    public function requestPaymentLink(array $order): void
    {
        $baseUrl = config('services.payment.url');

        if (!$baseUrl) {
            throw new PaymentServiceUnavailableException('Servico de pagamento nao configurado (PAYMENT_SERVICE_URL).');
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.payment.timeout', 5))
                ->post(rtrim($baseUrl, '/') . '/orders', $order);
        } catch (ConnectionException $e) {
            $this->recordFailure($order, $e->getMessage());

            throw new PaymentServiceUnavailableException('Nao foi possivel conectar ao servico de pagamento.', 0, $e);
        }

        if ($response->failed()) {
            $reason = (string) ($response->json('message') ?? $response->body());
            $this->recordFailure($order, "HTTP {$response->status()}: {$reason}");

            throw new PaymentServiceUnavailableException(
                "Servico de pagamento recusou a solicitacao (HTTP {$response->status()}): {$reason}"
            );
        }

        BusinessTelemetry::integration('payment_link_requested', true, [
            'payment_order_id' => $order['order_id'] ?? null,
            'idempotency_key' => $response->json('idempotency_key'),
        ]);
    }

    private function recordFailure(array $order, string $error): void
    {
        BusinessTelemetry::integration('payment_link_requested', false, [
            'payment_order_id' => $order['order_id'] ?? null,
            'error' => $error,
        ]);
    }
}
