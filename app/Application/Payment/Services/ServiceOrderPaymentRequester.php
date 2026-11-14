<?php

namespace App\Application\Payment\Services;

use App\Domain\Customer\Entities\Customer;
use App\Domain\Customer\ValueObjects\Document;
use App\Domain\Payment\Interfaces\PaymentGatewayInterface;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Support\Observability\BusinessTelemetry;

/**
 * Monta o pedido de cobrança do orçamento de uma OS e envia ao payment-service.
 */
class ServiceOrderPaymentRequester
{
    public const SOURCE_SERVICE = 'tech-challenge-application';

    public function __construct(
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function request(ServiceOrder $serviceOrder, Customer $customer): void
    {
        if ($serviceOrder->paymentOrderId === null || $serviceOrder->paymentCallbackToken === null) {
            throw new \DomainException('Ordem de servico sem cobranca aberta para o orcamento.');
        }

        $items = $this->buildItems($serviceOrder);
        $amountCents = array_sum(array_map(
            static fn (array $item): int => $item['quantity'] * $item['unit_price_cents'],
            $items
        ));

        if ($amountCents <= 0) {
            throw new \DomainException('Orcamento sem valor a cobrar.');
        }

        $document = Document::normalize($customer->document);

        if (!in_array(strlen($document), [11, 14], true)) {
            throw new \DomainException('Cliente sem CPF/CNPJ valido para pagamento.');
        }

        $payload = [
            'order_id' => $serviceOrder->paymentOrderId,
            'amount_cents' => $amountCents,
            'currency' => 'BRL',
            'description' => "Orcamento OS {$serviceOrder->id}",
            'customer' => array_filter([
                'name' => $customer->name,
                'email' => $customer->email,
                'cpf' => $document,
                'document_type' => strlen($document) === 14 ? 'CNPJ' : 'CPF',
                'phone' => $this->normalizePhone($customer->phone),
            ], static fn ($value) => $value !== null),
            'items' => $items,
            'metadata' => [
                'source_service' => self::SOURCE_SERVICE,
                'service_order_id' => $serviceOrder->id,
            ],
            'created_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'callback_url' => $this->callbackUrl($serviceOrder->paymentCallbackToken),
            // Enviado em cada pedido: o payment-service não precisa conhecer a URL do app no deploy
            'notification_url' => $this->notificationUrl(),
        ];

        $this->paymentGateway->requestPaymentLink($payload);

        BusinessTelemetry::serviceOrder('service_order_payment_requested', $serviceOrder, [
            'payment_order_id' => $serviceOrder->paymentOrderId,
            'amount_cents' => $amountCents,
        ]);
    }

    public function callbackUrl(string $token): string
    {
        return $this->publicBaseUrl() . "/api/payment/callback/{$token}";
    }

    public function notificationUrl(): string
    {
        return $this->publicBaseUrl() . '/api/payment/notification';
    }

    /**
     * URL pela qual o payment-service (AWS) alcança este app. Precisa ser pública.
     */
    private function publicBaseUrl(): string
    {
        $baseUrl = config('services.payment.callback_base_url') ?: config('app.url');

        return rtrim((string) $baseUrl, '/');
    }

    /**
     * Converte serviços e peças da OS em itens do payment-service (valores em centavos).
     * Quantidades fracionadas viram 1 unidade com o subtotal da linha, pois o gateway exige inteiro.
     *
     * @return array<int, array{id: string, title: string, quantity: int, unit_price_cents: int}>
     */
    private function buildItems(ServiceOrder $serviceOrder): array
    {
        $lines = [];

        foreach ([[$serviceOrder->services, 'service_id', 'service', 'Servico'], [$serviceOrder->items, 'item_id', 'item', 'Peca']] as [$rows, $idKey, $nestedKey, $fallbackTitle]) {
            foreach ($rows as $row) {
                $quantity = (float) ($row['quantity'] ?? 0);
                $unitPrice = (float) ($row['unit_price'] ?? 0);
                $title = (string) ($row['name'] ?? $row[$nestedKey]['name'] ?? $fallbackTitle);

                if (floor($quantity) === $quantity) {
                    $lineQuantity = (int) $quantity;
                    $unitPriceCents = (int) round($unitPrice * 100);
                } else {
                    $lineQuantity = 1;
                    $unitPriceCents = (int) round($quantity * $unitPrice * 100);
                    $title .= " (x{$quantity})";
                }

                if ($lineQuantity < 1 || $unitPriceCents < 1) {
                    continue;
                }

                $lines[] = [
                    'id' => (string) ($row[$idKey] ?? $nestedKey),
                    'title' => mb_substr($title, 0, 256),
                    'quantity' => $lineQuantity,
                    'unit_price_cents' => $unitPriceCents,
                ];
            }
        }

        return $lines;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (preg_match('/^55\d{10,11}$/', $digits)) {
            return "+{$digits}";
        }

        if (preg_match('/^\d{10,11}$/', $digits)) {
            return "+55{$digits}";
        }

        return null;
    }
}
