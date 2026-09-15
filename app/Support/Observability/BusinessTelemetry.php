<?php

namespace App\Support\Observability;

use Illuminate\Support\Facades\Log;

class BusinessTelemetry
{
    private static function requestContextValue(string $key, mixed $fallback = null): mixed
    {
        $request = function_exists('request') ? request() : null;

        if (!$request) {
            return $fallback;
        }

        $attributes = method_exists($request, 'attributes') ? $request->attributes : null;

        if ($attributes && $attributes->has($key)) {
            return $attributes->get($key);
        }

        if (method_exists($request, 'header')) {
            return $request->header($key) ?? $request->headers->get($key) ?? $fallback;
        }

        return $fallback;
    }

    public static function serviceOrder(string $event, object $serviceOrder, array $context = []): void
    {
        $request = function_exists('request') ? request() : null;

        $payload = [
            'event' => $event,
            'service_order_id' => $serviceOrder->id ?? null,
            'customer_id' => $serviceOrder->customerId ?? null,
            'vehicle_id' => $serviceOrder->vehicleId ?? null,
            'status' => $serviceOrder->status ?? null,
            'status_started_at' => $serviceOrder->statusStartedAt ?? null,
            'request_id' => self::requestContextValue('request_id'),
            'request_method' => $request?->method() ?? null,
            'request_path' => $request?->path() ?? null,
            'request_ip' => $request?->ip() ?? null,
            'request_user_agent' => $request?->userAgent() ?? null,
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        Log::info('service_order_event', array_merge($payload, $context));
    }

    public static function statusTransition(string $previousStatus, string $newStatus, object $serviceOrder, array $context = []): void
    {
        if (isset($context['status_duration_seconds'])) {
            $context['status_duration_seconds'] = (int) $context['status_duration_seconds'];
        }

        $payload = [
            'event' => 'service_order_status_timeline',
            'service_order_id' => $serviceOrder->id ?? null,
            'customer_id' => $serviceOrder->customerId ?? null,
            'vehicle_id' => $serviceOrder->vehicleId ?? null,
            'previous_status' => $previousStatus,
            'status' => $newStatus,
            'status_started_at' => $serviceOrder->statusStartedAt ?? null,
            'previous_status_started_at' => $context['previous_status_started_at'] ?? null,
            'request_id' => self::requestContextValue('request_id'),
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        Log::info('service_order_status_timeline', array_merge($payload, $context));

        if (function_exists('newrelic_record_custom_event')) {
            newrelic_record_custom_event('ServiceOrderStatusDuration', [
                'service_order_id' => $serviceOrder->id ?? null,
                'customer_id' => $serviceOrder->customerId ?? null,
                'vehicle_id' => $serviceOrder->vehicleId ?? null,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'status_duration_seconds' => (int) ($context['status_duration_seconds'] ?? 0),
                'previous_status_started_at' => $context['previous_status_started_at'] ?? null,
                'new_status_started_at' => $serviceOrder->statusStartedAt ?? null,
                'request_id' => self::requestContextValue('request_id'),
                'namespace_name' => env('POD_NAMESPACE', 'unknown'),
                'pod_name' => gethostname(),
            ]);
        }

        if (function_exists('newrelic_record_metric')) {
            newrelic_record_metric('Custom/ServiceOrder/StatusTransition/' . strtoupper($newStatus), 1);
            newrelic_record_metric('Custom/ServiceOrder/StatusDuration/' . strtoupper($newStatus), (float) ($context['status_duration_seconds'] ?? 0));
        }
    }

    public static function healthCheck(string $status, array $context = []): void
    {
        $attributes = [
            'status' => $status,
            'service' => $context['service'] ?? 'tech-challenge-application',
            'uptime_seconds' => (int) ($context['uptime_seconds'] ?? 0),
            'request_id' => self::requestContextValue('request_id'),
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        if (function_exists('newrelic_record_custom_event')) {
            newrelic_record_custom_event('ServiceHealth', $attributes);
        }

        if (function_exists('newrelic_record_metric')) {
            newrelic_record_metric('Custom/Service/Health', $status === 'ok' ? 1 : 0);
            newrelic_record_metric('Custom/Service/UptimeSeconds', (float) $attributes['uptime_seconds']);
        }

        Log::info('service_health_check', $attributes);
    }

    public static function serviceOrderCreated(object $serviceOrder, array $context = []): void
    {
        $attributes = [
            'service_order_id' => $serviceOrder->id ?? null,
            'customer_id' => $serviceOrder->customerId ?? null,
            'vehicle_id' => $serviceOrder->vehicleId ?? null,
            'status' => $serviceOrder->status ?? null,
            'send_quote' => $context['send_quote'] ?? false,
            'request_id' => self::requestContextValue('request_id'),
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        if (function_exists('newrelic_record_custom_event')) {
            newrelic_record_custom_event('ServiceOrderCreated', $attributes);
        }

        self::serviceOrder('service_order_created', $serviceOrder, $context);
    }

    public static function serviceOrderError(string $message, array $context = []): void
    {
        $attributes = [
            'service_order_id' => $context['service_order_id'] ?? null,
            'customer_id' => $context['customer_id'] ?? null,
            'vehicle_id' => $context['vehicle_id'] ?? null,
            'error' => $message,
            'error_type' => $context['error_type'] ?? 'DomainException',
            'request_id' => self::requestContextValue('request_id'),
            'request_path' => $context['request_path'] ?? null,
            'request_method' => $context['request_method'] ?? null,
            'namespace_name' => env('POD_NAMESPACE', 'unknown'),
            'pod_name' => gethostname(),
        ];

        if (function_exists('newrelic_record_custom_event')) {
            newrelic_record_custom_event('ServiceOrderError', $attributes);
        }

        if (function_exists('newrelic_notice_error')) {
            newrelic_notice_error('Service order error: ' . $message, new \RuntimeException($message));
        }

        Log::warning('service_order_error', $attributes);
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
