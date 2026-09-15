<?php
namespace Tests\Feature {

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderStatusUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
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

    public function test_status_duration_is_measured_from_previous_status_timestamp(): void
    {
        Log::spy();

        Carbon::setTestNow('2026-09-13 12:00:00');

        $serviceOrder = ServiceOrder::create('customer-3', 'vehicle-3', [], []);
        $serviceOrder->status = ServiceOrder::STATUS_EM_DIAGNOSTICO;
        $serviceOrder->statusStartedAt = '2026-09-13 11:55:00';

        $repository = Mockery::mock(ServiceOrderRepositoryInterface::class);
        $repository->shouldReceive('findById')->with($serviceOrder->id)->andReturn($serviceOrder);
        $repository->shouldReceive('update')->once()->with(Mockery::on(function (ServiceOrder $updatedOrder) {
            return $updatedOrder->status === ServiceOrder::STATUS_EM_EXECUCAO;
        }));

        $useCase = new UpdateServiceOrderStatusUseCase($repository);
        $useCase->execute(new UpdateServiceOrderStatusDTO($serviceOrder->id, ServiceOrder::STATUS_EM_EXECUCAO));

        Log::shouldHaveReceived('info')->with(
            'service_order_status_timeline',
            Mockery::on(function (array $payload) {
                return isset($payload['status_duration_seconds'])
                    && $payload['status_duration_seconds'] === 300;
            })
        );
    }

    public function test_business_telemetry_records_custom_event_for_status_duration(): void
    {
        $GLOBALS['__nr_custom_events'] = [];

        $serviceOrder = (object) [
            'id' => 'os-321',
            'customerId' => 'customer-3',
            'vehicleId' => 'vehicle-3',
            'status' => 'em_execucao',
            'statusStartedAt' => '2026-09-13T09:00:00+00:00',
        ];

        \App\Support\Observability\BusinessTelemetry::statusTransition(
            'em_diagnostico',
            'em_execucao',
            $serviceOrder,
            [
                'status_duration_seconds' => 1800,
                'previous_status_started_at' => '2026-09-13T08:30:00+00:00',
            ]
        );

        $this->assertCount(1, $GLOBALS['__nr_custom_events']);
        $this->assertSame('ServiceOrderStatusDuration', $GLOBALS['__nr_custom_events'][0]['name']);
        $this->assertSame('em_diagnostico', $GLOBALS['__nr_custom_events'][0]['attributes']['previous_status']);
        $this->assertSame('em_execucao', $GLOBALS['__nr_custom_events'][0]['attributes']['new_status']);
        $this->assertSame(1800, $GLOBALS['__nr_custom_events'][0]['attributes']['status_duration_seconds']);
    }

    public function test_business_telemetry_records_custom_event_for_healthcheck(): void
    {
        $GLOBALS['__nr_custom_events'] = [];

        \App\Support\Observability\BusinessTelemetry::healthCheck('ok', [
            'service' => 'tech-challenge-application',
            'uptime_seconds' => 3600,
        ]);

        $this->assertCount(1, $GLOBALS['__nr_custom_events']);
        $this->assertSame('ServiceHealth', $GLOBALS['__nr_custom_events'][0]['name']);
        $this->assertSame('ok', $GLOBALS['__nr_custom_events'][0]['attributes']['status']);
        $this->assertSame(3600, $GLOBALS['__nr_custom_events'][0]['attributes']['uptime_seconds']);
    }

    public function test_business_telemetry_records_custom_event_for_service_order_creation(): void
    {
        $GLOBALS['__nr_custom_events'] = [];

        $serviceOrder = (object) [
            'id' => 'os-789',
            'customerId' => 'customer-9',
            'vehicleId' => 'vehicle-9',
            'status' => 'em_aberto',
        ];

        \App\Support\Observability\BusinessTelemetry::serviceOrderCreated($serviceOrder, [
            'send_quote' => true,
        ]);

        $this->assertCount(1, $GLOBALS['__nr_custom_events']);
        $this->assertSame('ServiceOrderCreated', $GLOBALS['__nr_custom_events'][0]['name']);
        $this->assertSame('os-789', $GLOBALS['__nr_custom_events'][0]['attributes']['service_order_id']);
        $this->assertSame('customer-9', $GLOBALS['__nr_custom_events'][0]['attributes']['customer_id']);
    }

    public function test_business_telemetry_records_custom_event_for_service_order_error(): void
    {
        $GLOBALS['__nr_custom_events'] = [];

        \App\Support\Observability\BusinessTelemetry::serviceOrderError(
            'Cliente nao encontrado.',
            [
                'service_order_id' => 'os-999',
                'customer_id' => 'customer-error',
                'vehicle_id' => 'vehicle-error',
                'error_type' => 'DomainException',
                'request_path' => 'api/service-order',
                'request_method' => 'POST',
            ]
        );

        $this->assertCount(1, $GLOBALS['__nr_custom_events']);
        $this->assertSame('ServiceOrderError', $GLOBALS['__nr_custom_events'][0]['name']);
        $this->assertSame('os-999', $GLOBALS['__nr_custom_events'][0]['attributes']['service_order_id']);
        $this->assertSame('Cliente nao encontrado.', $GLOBALS['__nr_custom_events'][0]['attributes']['error']);
    }
}

}
