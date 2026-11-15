<?php

namespace App\Infrastructure\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceOrderStatusOutbox
{
    public function record(string $orderId, string $status, string $previousStatus): void
    {
        if (! config('services_ms.enabled')) {
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'type' => 'ServiceOrderStatusChanged',
            'version' => 1,
            'occurred_at' => now()->toISOString(),
            'service_order_id' => $orderId,
            'previous_status' => $previousStatus,
            'status' => $status,
        ];

        $id = DB::table('outbox')->insertGetId([
            'event_id' => $eventId,
            'type' => $payload['type'],
            'group_id' => $orderId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload['sequence'] = $id;
        DB::table('outbox')->where('id', $id)->update([
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }
}
