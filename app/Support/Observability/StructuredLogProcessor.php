<?php

namespace App\Support\Observability;

use Monolog\Processor\ProcessorInterface;

class StructuredLogProcessor implements ProcessorInterface
{
    public function __invoke(array $record): array
    {
        $context = $record['context'] ?? [];
        $requestId = $context['request_id']
            ?? request()->attributes->get('request_id')
            ?? request()->header('X-Request-Id');

        $record['event'] = $context['event'] ?? $record['message'] ?? 'application_event';
        $record['message'] = $record['message'] ?? ($context['event'] ?? 'application_event');
        $record['request_id'] = $requestId;
        $record['app'] = env('APP_NAME', 'tech-challenge');
        $record['environment'] = env('APP_ENV', 'production');
        $record['service'] = env('APP_NAME', 'tech-challenge');
        $record['namespace_name'] = env('POD_NAMESPACE', 'unknown');
        $record['pod_name'] = gethostname();
        $record['context'] = array_merge($context, [
            'app' => $record['app'],
            'environment' => $record['environment'],
            'service' => $record['service'],
            'request_id' => $requestId,
            'namespace_name' => $record['namespace_name'],
            'pod_name' => $record['pod_name'],
        ]);

        return $record;
    }
}
