<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Item\Entities\StockMovement;
use App\Domain\Item\Interfaces\StockMovementRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\StockMovementModel;
use Illuminate\Support\Facades\Auth;

class StockMovementRepositoryEloquent implements StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void
    {
        StockMovementModel::create([
            'id'               => $movement->id,
            'item_id'          => $movement->itemId,
            'movement_type'    => $movement->movementType->getValue(),
            'quantity'         => $movement->quantity,
            'previous_quantity'=> $movement->previousQuantity,
            'current_quantity' => $movement->currentQuantity,
            'reason'           => $movement->reason,
            'notes'            => $movement->notes,
            'created_user_id'  => Auth::id(),
            'created_at'       => now(),
        ]);
    }

    public function findByItemId(string $itemId, int $page, int $perPage): array
    {
        return StockMovementModel::where('item_id', $itemId)
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();
    }
}
