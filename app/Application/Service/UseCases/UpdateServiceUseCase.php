<?php

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\UpdateServiceDTO;
use App\Domain\Service\Entities\Service;
use App\Domain\Service\Interfaces\ServiceRepositoryInterface;

class UpdateServiceUseCase
{
    public function __construct(
        private ServiceRepositoryInterface $repository
    ) {}

    public function execute(UpdateServiceDTO $dto): Service
    {
        $service = $this->repository->findById($dto->id);

        if (!$service) {
            throw new \Exception('Serviço não encontrado');
        }

        if (is_null($dto->name) && is_null($dto->price)) {
            throw new \Exception('Nenhum dado para atualizar');
        }


        if (!is_null($dto->name)) {
            $existing = $this->repository->findByName($dto->name);

            if ($existing && $existing->id !== $service->id) {
                throw new \Exception('Já existe um serviço com esse nome');
            }
        }

        $service->updateData(
            $dto->name,
            $dto->price
        );

        $this->repository->update($service);

        return $service;
    }
}

