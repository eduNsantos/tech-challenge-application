<?php

namespace App\Infrastructure\Messaging;

use App\Domain\ServiceOrder\Entities\ServiceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceOrderCreatedOutbox
{
    /**
     * @param  array<int, array{id: string, service_id: string, name: string, quantity: float|int, unit_price: float}>  $services
     */
    public function record(ServiceOrder $serviceOrder, array $services): void
    {
        if (! config('services_ms.enabled')) {
            return;
        }

        $eventId = (string) Str::uuid();

        $payload = [
            'event_id' => $eventId,
            'type' => 'ServiceOrderCreated',
            'version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'service_order' => [
                'id' => $serviceOrder->id,
                'customer_id' => $serviceOrder->customerId,
                'vehicle_id' => $serviceOrder->vehicleId,
                'status' => $serviceOrder->status,
                'services_total' => $serviceOrder->servicesTotal,
            ],
            'services' => array_map(fn (array $s): array => [
                'id' => $s['id'],
                'service_id' => $s['service_id'],
                'name' => $s['name'],
                'price' => (float) $s['unit_price'],
                'quantity' => $s['quantity'],
            ], $services),
        ];

        DB::table('outbox')->insert([
            'event_id' => $eventId,
            'type' => 'ServiceOrderCreated',
            'group_id' => $serviceOrder->id,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
