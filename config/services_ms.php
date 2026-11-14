<?php

return [
    // Quando false, a criação da O.S. não publica evento para o MS de serviços.
    'enabled' => (bool) env('SERVICES_MS_ENABLED', false),

    'sqs' => [
        'endpoint' => env('SQS_ENDPOINT'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'service_orders_created_url' => env('SQS_SERVICE_ORDERS_CREATED_URL'),
        'service_order_status_changed_url' => env('SQS_SERVICE_ORDER_STATUS_CHANGED_URL'),
    ],
];
