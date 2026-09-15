<?php
namespace Tests\Feature {

use App\Application\ServiceOrder\DTOs\UpdateServiceOrderStatusDTO;
use App\Application\ServiceOrder\UseCases\UpdateServiceOrderStatusUseCase;
use App\Domain\ServiceOrder\Entities\ServiceOrder;
use App\Domain\ServiceOrder\Interfaces\ServiceOrderRepositoryInterface;
use App\Support\Observability\BusinessTelemetry;
use App\Support\Observability\NewRelicTelemetry;
use App\Support\Observability\TelemetryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class BusinessTelemetryTest extends TestCase
{
    protected function tearDown(): void
    {
        BusinessTelemetry::setTelemetry(new NewRelicTelemetry());

        parent::tearDown();
    }

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
        $telemetry = Mockery::mock(TelemetryInterface::class);
        $telemetry
            ->shouldReceive('customEvent')
            ->once()
            ->with(
                'ServiceOrderStatusDuration',
                Mockery::on(function (array $attributes) {
                    return $attributes['previous_status'] === 'em_diagnostico'
                        && $attributes['new_status'] === 'em_execucao'
                        && $attributes['status_duration_seconds'] === 1800;
                })
            );
        $telemetry
            ->shouldReceive('metric')
            ->twice();

        BusinessTelemetry::setTelemetry($telemetry);

        $serviceOrder = (object) [
            'id' => 'os-321',
            'customerId' => 'customer-3',
            'vehicleId' => 'vehicle-3',
            'status' => 'em_execucao',
            'statusStartedAt' => '2026-09-13T09:00:00+00:00',
        ];

        BusinessTelemetry::statusTransition(
            'em_diagnostico',
            'em_execucao',
            $serviceOrder,
            [
                'status_duration_seconds' => 1800,
                'previous_status_started_at' => '2026-09-13T08:30:00+00:00',
            ]
        );
    }

    public function test_business_telemetry_records_custom_event_for_healthcheck(): void
    {
        $telemetry = Mockery::mock(TelemetryInterface::class);
        $telemetry
            ->shouldReceive('customEvent')
            ->once()
            ->with(
                'ServiceHealth',
                Mockery::on(function (array $attributes) {
                    return $attributes['status'] === 'ok'
                        && $attributes['uptime_seconds'] === 3600;
                })
            );
        $telemetry
            ->shouldReceive('metric')
            ->twice();

        BusinessTelemetry::setTelemetry($telemetry);

        BusinessTelemetry::healthCheck('ok', [
            'service' => 'tech-challenge-application',
            'uptime_seconds' => 3600,
        ]);
    }

    public function test_business_telemetry_records_custom_event_for_service_order_creation(): void
    {
        $telemetry = Mockery::mock(TelemetryInterface::class);
        $telemetry
            ->shouldReceive('customEvent')
            ->once()
            ->with(
                'ServiceOrderCreated',
                Mockery::on(function (array $attributes) {
                    return $attributes['service_order_id'] === 'os-789'
                        && $attributes['customer_id'] === 'customer-9';
                })
            );

        BusinessTelemetry::setTelemetry($telemetry);

        $serviceOrder = (object) [
            'id' => 'os-789',
            'customerId' => 'customer-9',
            'vehicleId' => 'vehicle-9',
            'status' => 'em_aberto',
        ];

        BusinessTelemetry::serviceOrderCreated($serviceOrder, [
            'send_quote' => true,
        ]);
    }

    public function test_business_telemetry_records_custom_event_for_service_order_error(): void
    {
        $telemetry = Mockery::mock(TelemetryInterface::class);
        $telemetry
            ->shouldReceive('customEvent')
            ->once()
            ->with(
                'ServiceOrderError',
                Mockery::on(function (array $attributes) {
                    return $attributes['service_order_id'] === 'os-999'
                        && $attributes['error'] === 'Cliente nao encontrado.';
                })
            );
        $telemetry
            ->shouldReceive('noticeError')
            ->once();

        BusinessTelemetry::setTelemetry($telemetry);

        BusinessTelemetry::serviceOrderError(
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
    }
}

}
