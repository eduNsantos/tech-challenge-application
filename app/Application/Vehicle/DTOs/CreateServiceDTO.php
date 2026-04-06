<?php

namespace App\Application\Vehicle\DTOs;

class CreateServiceDTO
{
    public function __construct(
        public string $name,
        public int $price
    ) {}
}
