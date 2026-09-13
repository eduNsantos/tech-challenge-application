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
            'status_started_at' => $serviceOrder->statusStartedAt ?? null,
            'request_id' => request()->attributes->get('request_id') ?? request()->header('X-Request-Id'),
            'request_method' => request()->method(),
            'request_path' => request()->path(),
            'request_ip' => request()->ip(),
            'request_user_agent' => request()->userAgent(),
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        Log::info('service_order_event', array_merge($payload, $context));
    }

    public static function statusTransition(string $previousStatus, string $newStatus, object $serviceOrder, array $context = []): void
    {
        $payload = [
            'event' => 'service_order_status_timeline',
            'service_order_id' => $serviceOrder->id ?? null,
            'customer_id' => $serviceOrder->customerId ?? null,
            'vehicle_id' => $serviceOrder->vehicleId ?? null,
            'previous_status' => $previousStatus,
            'status' => $newStatus,
            'status_started_at' => $serviceOrder->statusStartedAt ?? null,
            'request_id' => request()->attributes->get('request_id') ?? request()->header('X-Request-Id'),
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        Log::info('service_order_status_timeline', array_merge($payload, $context));

        if (function_exists('newrelic_record_metric')) {
            newrelic_record_metric('Custom/ServiceOrder/StatusTransition/' . strtoupper($newStatus), 1);
            newrelic_record_metric('Custom/ServiceOrder/StatusDuration/' . strtoupper($newStatus), (float) ($context['status_duration_seconds'] ?? 0));
        }
    }

    public static function integration(string $name, bool $success, array $context = []): void
    {
        $payload = [
            'event' => $success ? 'integration_success' : 'integration_failed',
            'integration_name' => $name,
            'success' => $success,
            'request_id' => request()->attributes->get('request_id') ?? request()->header('X-Request-Id'),
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
            'status' => $success ? 'success' : 'failed',
        ];

        if ($success) {
            Log::info('integration_event', array_merge($payload, $context));

            if (function_exists('newrelic_record_metric')) {
                newrelic_record_metric('Custom/Integration/' . strtoupper(str_replace(['-', '_', ' '], '', $name)) . '/Success', 1);
            }

            return;
        }

        Log::warning('integration_event', array_merge($payload, $context));

        if (function_exists('newrelic_record_metric')) {
            newrelic_record_metric('Custom/Integration/' . strtoupper(str_replace(['-', '_', ' '], '', $name)) . '/Failure', 1);
        }

        if (function_exists('newrelic_notice_error')) {
            newrelic_notice_error('Integration failure: ' . $name, new \RuntimeException($context['error'] ?? 'Integration failure'));
        }
    }
}
