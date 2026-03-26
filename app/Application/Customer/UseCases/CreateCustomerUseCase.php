<?php

namespace App\Application\Customer\UseCases;

use App\Application\Customer\DTOs\CreateCustomerDTO;
use App\Domain\Customer\interfaces\CustomerRepositoryInterface;
use App\Domain\Customer\Entities\Customer;

class CreateCustomerUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $repository
    ) {}

    public function execute(CreateCustomerDTO $dto): Customer
    {
        $customer = Customer::create(
            $dto->name,
            $dto->email,
            $dto->phone,
            $dto->document
        );
        $this->repository->save($customer);
        return $customer;
    }
}