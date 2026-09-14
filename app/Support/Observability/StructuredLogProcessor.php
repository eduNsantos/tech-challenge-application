<?php

namespace App\Support\Observability;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class StructuredLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $requestId = $context['request_id']
            ?? request()->attributes->get('request_id')
            ?? request()->header('X-Request-Id');

        $message = $record->message;
        $event = $context['event'] ?? $message ?? 'application_event';
        $record->extra['event'] = $event;
        $record->extra['request_id'] = $requestId;
        $record->extra['app'] = env('APP_NAME', 'tech-challenge');
        $record->extra['environment'] = env('APP_ENV', 'production');
        $record->extra['service'] = env('APP_NAME', 'tech-challenge');
        $record->extra['namespace_name'] = env('POD_NAMESPACE', 'unknown');
        $record->extra['pod_name'] = gethostname();
        $record->extra['context'] = array_merge($context, [
            'app' => $record->extra['app'],
            'environment' => $record->extra['environment'],
            'service' => $record->extra['service'],
            'request_id' => $requestId,
            'namespace_name' => $record->extra['namespace_name'],
            'pod_name' => $record->extra['pod_name'],
        ]);

        return $record;
    }
}
