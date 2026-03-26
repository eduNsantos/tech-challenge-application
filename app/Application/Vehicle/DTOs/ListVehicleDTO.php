<?php

namespace App\Application\Vehicle\DTOs;

class ListVehicleDTO
{
    public function __construct(
        public ?int $page,
        public ?int $perPage
    ) {}
}
