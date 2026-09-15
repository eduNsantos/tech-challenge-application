<?php

namespace App\Support\Observability;

interface TelemetryInterface
{
    public function customEvent(string $name, array $attributes): void;

    public function metric(string $name, float|int $value): void;

    public function noticeError(string $message, \Throwable $exception): void;
}
