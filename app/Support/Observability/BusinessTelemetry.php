<?php

namespace App\Support\Observability;

use Illuminate\Support\Facades\Log;

class BusinessTelemetry
{
    public static function serviceOrder(string $event, object $serviceOrder, array $context = []): void
    {
        $payload = [
            'event' => $event,
            'service_order_id' => $serviceOrder->id ?? null,
            'customer_id' => $serviceOrder->customerId ?? null,
            'vehicle_id' => $serviceOrder->vehicleId ?? null,
            'status' => $serviceOrder->status ?? null,
            'request_id' => request()->attributes->get('request_id') ?? request()->header('X-Request-Id'),
        ];

        Log::info('service_order_event', array_merge($payload, $context));
    }

    public static function integration(string $name, bool $success, array $context = []): void
    {
        $payload = [
            'integration_name' => $name,
            'success' => $success,
            'request_id' => request()->attributes->get('request_id') ?? request()->header('X-Request-Id'),
        ];

        if ($success) {
            Log::info('integration_event', array_merge($payload, $context));
            return;
        }

        Log::warning('integration_event', array_merge($payload, $context));
    }
}
