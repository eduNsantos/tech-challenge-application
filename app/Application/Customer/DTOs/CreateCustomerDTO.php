<?php

namespace App\Application\Customer\DTOs;

class CreateCustomerDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $document
    ) {}
}
