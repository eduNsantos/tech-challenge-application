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

    public function test_business_telemetry_logs_status_timeline_metrics(): void
    {
        Log::spy();

        $serviceOrder = (object) [
            'id' => 'os-456',
            'customerId' => 'customer-2',
            'vehicleId' => 'vehicle-2',
            'status' => 'em_execucao',
            'statusStartedAt' => '2026-09-13T09:00:00+00:00',
        ];

        \App\Support\Observability\BusinessTelemetry::statusTransition(
            'em_diagnostico',
            'em_execucao',
            $serviceOrder,
            [
                'status_duration_seconds' => 3600,
            ]
        );

        Log::shouldHaveReceived('info')->with(
            'service_order_status_timeline',
            \Mockery::on(fn ($payload) =>
                ($payload['event'] ?? null) === 'service_order_status_timeline'
                && ($payload['status'] ?? null) === 'em_execucao'
                && ($payload['previous_status'] ?? null) === 'em_diagnostico'
                && ($payload['status_duration_seconds'] ?? null) === 3600
            )
        );
    }
}
