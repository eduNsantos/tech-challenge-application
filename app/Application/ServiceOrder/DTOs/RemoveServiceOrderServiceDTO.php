<?php

namespace App\Application\ServiceOrder\DTOs;

class RemoveServiceOrderServiceDTO
{
    public function __construct(
        public string $id,
        public string $serviceId
    ) {}
}
