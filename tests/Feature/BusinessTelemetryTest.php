<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BusinessTelemetryTest extends TestCase
{
    public function test_business_telemetry_logs_service_order_events(): void
    {
        Log::spy();

        $serviceOrder = (object) [
            'id' => 'os-123',
            'customerId' => 'customer-1',
            'vehicleId' => 'vehicle-1',
            'status' => 'em_execucao',
        ];

        \App\Support\Observability\BusinessTelemetry::serviceOrder('service_order_created', $serviceOrder, [
            'send_quote' => true,
        ]);

        Log::shouldHaveReceived('info')->with(
            'service_order_event',
            \Mockery::on(fn ($payload) => isset($payload['event']) && $payload['event'] === 'service_order_created' && $payload['service_order_id'] === 'os-123')
        );
    }
}
