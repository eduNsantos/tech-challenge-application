<?php

namespace App\Domain\Item\Entities;

use App\Domain\Item\ValueObjects\MovementType;
use Illuminate\Support\Str;

class StockMovement
{
    // TODO: adicionar propriedade ?string $serviceOrderId para associar saidas a uma OS.
    public function __construct(
        public string $id,
        public string $itemId,
        public MovementType $movementType,
        public float $quantity,
        public float $previousQuantity,
        public float $currentQuantity,
        public string $reason,
        public ?string $notes
    ) {}

    public static function record(
        string $itemId,
        MovementType $movementType,
        float $quantity,
        float $previousQuantity,
        float $currentQuantity,
        string $reason,
        ?string $notes
    ): self {
        return new self(
            id: Str::uuid()->toString(),
            itemId: $itemId,
            movementType: $movementType,
            quantity: $quantity,
            previousQuantity: $previousQuantity,
            currentQuantity: $currentQuantity,
            reason: $reason,
            notes: $notes
        );
    }
}
