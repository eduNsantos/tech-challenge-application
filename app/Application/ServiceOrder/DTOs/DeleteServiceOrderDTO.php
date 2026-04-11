<?php

namespace App\Application\ServiceOrder\DTOs;

class DeleteServiceOrderDTO
{
    public function __construct(
        public string $id
    ) {}
}
