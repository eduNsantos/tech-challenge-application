<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Item\Entities\Item;
use App\Domain\Item\Interfaces\ItemRepositoryInterface;
use App\Domain\Item\ValueObjects\ItemCode;
use App\Domain\Item\ValueObjects\ItemType;
use App\Domain\Item\ValueObjects\MeasureUnit;
use App\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use Illuminate\Support\Facades\Auth;

class ItemRepositoryEloquent implements ItemRepositoryInterface
{
    public function save(Item $item): void
    {
        ItemModel::create([
            'id'               => $item->id,
            'name'             => $item->name,
            'code'             => $item->code->getValue(),
            'type'             => $item->type->getValue(),
            'description'      => $item->description,
            'measure_unit'     => $item->measureUnit->getValue(),
            'stock_quantity'   => $item->stockQuantity,
            'minimum_quantity' => $item->minimumQuantity,
            'unit_price'       => $item->unitPrice,
            'created_user_id'  => Auth::id(),
            'updated_user_id'  => Auth::id(),
        ]);
    }

    public function findById(string $id): ?Item
    {
        $model = ItemModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByCode(string $code): ?Item
    {
        $model = ItemModel::where('code', strtoupper($code))->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function paginate(int $page, int $perPage, ?string $type): array
    {
        $query = ItemModel::query();

        if ($type !== null) {
            $query->where('type', $type);
        }

        $total = $query->count();
        $data  = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->toArray();

        return [
            'data'    => $data,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    public function findAll(?string $type): array
    {
        $query = ItemModel::query();

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get()->toArray();
    }

    public function update(Item $item): void
    {
        ItemModel::where('id', $item->id)->update([
            'name'             => $item->name,
            'code'             => $item->code->getValue(),
            'type'             => $item->type->getValue(),
            'description'      => $item->description,
            'measure_unit'     => $item->measureUnit->getValue(),
            'stock_quantity'   => $item->stockQuantity,
            'minimum_quantity' => $item->minimumQuantity,
            'unit_price'       => $item->unitPrice,
            'updated_user_id'  => Auth::id(),
        ]);
    }

    public function delete(string $id): void
    {
        ItemModel::where('id', $id)->delete();
    }

    private function toEntity(ItemModel $model): Item
    {
        return new Item(
            id: $model->id,
            name: $model->name,
            code: new ItemCode($model->code),
            type: new ItemType($model->type),
            measureUnit: new MeasureUnit($model->measure_unit),
            stockQuantity: (float) $model->stock_quantity,
            minimumQuantity: (float) $model->minimum_quantity,
            description: $model->description,
            unitPrice: $model->unit_price !== null ? (float) $model->unit_price : null
        );
    }
}
