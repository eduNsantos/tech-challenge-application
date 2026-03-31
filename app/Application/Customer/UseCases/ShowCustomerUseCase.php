<?php

namespace App\Application\Customer\UseCases;

use App\Application\Customer\DTOs\ShowCustomerDTO;
use App\Domain\Customer\interfaces\CustomerRepositoryInterface;
use App\Domain\Customer\Entities\Customer;

class ShowCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $repository
    ) {}
    public function execute(ShowCustomerDTO $dto): Customer
    {
        $customer = $this->repository->findById($dto->id);
        if (!$customer) {
            throw new \Exception('Cliente não encontrado');
        }
        return $customer;
    }
}