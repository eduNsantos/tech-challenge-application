<?php

namespace App\Application\ServiceOrder\DTOs;

class UpdateServiceOrderStatusDTO
{
    public function __construct(
        public string $id,
        public string $status
    ) {}
}
