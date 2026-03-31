<?php

namespace App\Application\Customer\DTOs;

class ListCustomerDTO
{
    public function __construct(
        public ?int $page,
        public ?int $perPage
    ) {}
}
