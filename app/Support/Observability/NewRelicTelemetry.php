<?php

namespace App\Support\Observability;

class NewRelicTelemetry implements TelemetryInterface
{
    public function customEvent(string $name, array $attributes): void
    {
        if (function_exists('newrelic_record_custom_event')) {
            newrelic_record_custom_event($name, $attributes);
        }
    }

    public function metric(string $name, float|int $value): void
    {
        if (function_exists('newrelic_record_metric')) {
            newrelic_record_metric($name, $value);
        }
    }

    public function noticeError(string $message, \Throwable $exception): void
    {
        if (function_exists('newrelic_notice_error')) {
            newrelic_notice_error($message, $exception);
        }
    }
}
