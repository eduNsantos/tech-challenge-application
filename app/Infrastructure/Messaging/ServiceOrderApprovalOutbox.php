<?php

namespace App\Infrastructure\Messaging;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceOrderApprovalOutbox
{
    public function record(ServiceOrder $serviceOrder, string $previousStatus): void
    {
        if (! config('services_ms.enabled')) {
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'type' => 'ServiceOrderApprovalChanged',
            'version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'service_order_id' => $serviceOrder->id,
            'status' => $serviceOrder->status,
            'previous_status' => $previousStatus,
        ];

        DB::table('outbox')->insert([
            'event_id' => $eventId,
            'type' => 'ServiceOrderApprovalChanged',
            'group_id' => $serviceOrder->id,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
