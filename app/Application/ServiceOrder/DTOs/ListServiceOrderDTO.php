<?php

namespace App\Application\ServiceOrder\DTOs;

class ListServiceOrderDTO
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 10
    ) {}
}
