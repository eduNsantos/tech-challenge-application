<?php

namespace App\Domain\ServiceOrder\Interfaces;

use App\Domain\ServiceOrder\Entities\ServiceOrder;

interface ServiceOrderRepositoryInterface
{
    public function save(ServiceOrder $serviceOrder): void;

    public function findById(string $id): ?ServiceOrder;

    public function findAll(): array;

    public function paginate(int $page, int $perPage): array;

    public function update(ServiceOrder $serviceOrder): void;

    public function delete(string $id): void;
}
