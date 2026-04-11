<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ServiceModel;
use Illuminate\Support\Facades\Auth;

class ServiceRepositoryEloquent implements ServiceRepositoryInterface
{
    public function save(Service $service): void
    {
        ServiceModel::create([
            'id' => $service->id,
            'name' => $service->name,
            'price' => $service->price,
            'created_user_id' => Auth::id(),
            'updated_user_id' => Auth::id()
        ]);
    }

    public function findByName(string $name): ?Service
    {
        $model = ServiceModel::where('name', $name)->first();

        if (!$model) return null;

        return new Service(
            $model->id,
            $model->name,
            $model->price
        );
    }

    public function paginate(int $page, int $perPage): array
    {
        return ServiceModel::query()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray()
        ;
    }

    public function findAll(): array
    {
        $models = ServiceModel::all()->toArray();

        return $models;
    }

    public function findById(string $id): ?Service
    {
        $model = ServiceModel::find($id);

        if (!$model) return null;

        return new Service(
            $model->id,
            $model->name,
            $model->price
        );
    }

    public function update(Service $service): void
    {
        ServiceModel::where('id', $service->id)->update([
            'name' => $service->name,
            'price' => $service->price,
            'updated_user_id' => Auth::id()
        ]);
    }
}