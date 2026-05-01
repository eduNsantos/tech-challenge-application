<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\StockMovementModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockMovementRepositoryEloquent implements StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void
    {
        $actorId = Auth::id()
            ?? DB::table('service_orders')->where('id', $movement->serviceOrderId)->value('updated_user_id')
            ?? DB::table('service_orders')->where('id', $movement->serviceOrderId)->value('created_user_id')
            ?? DB::table('items')->where('id', $movement->itemId)->value('updated_user_id')
            ?? DB::table('items')->where('id', $movement->itemId)->value('created_user_id')
            ?? DB::table('users')->value('id');

        StockMovementModel::create([
            'id'                => $movement->id,
            'item_id'           => $movement->itemId,
            'service_order_id'  => $movement->serviceOrderId,
            'movement_type'     => $movement->movementType->getValue(),
            'quantity'          => $movement->quantity,
            'previous_quantity' => $movement->previousQuantity,
            'current_quantity'  => $movement->currentQuantity,
            'reason'            => $movement->reason,
            'notes'             => $movement->notes,
            'created_user_id'   => $actorId,
            'created_at'        => now(),
        ]);
    }

    public function findByItemId(string $itemId, int $page, int $perPage): array
    {
        $query = StockMovementModel::where('item_id', $itemId)->orderBy('created_at', 'desc');
        $total = $query->count();
        $data  = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->map(fn ($m) => [
            'id'                => $m->id,
            'type'              => $m->movement_type,
            'quantity'          => (float) $m->quantity,
            'previous_quantity' => (float) $m->previous_quantity,
            'current_quantity'  => (float) $m->current_quantity,
            'reason'            => $m->reason,
            'notes'             => $m->notes,
            'created_at'        => $m->created_at,
        ])->values()->toArray();

        return [
            'data'    => $data,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }
}
