<?php

namespace App\Application\ServiceOrderService\DTOs;

class StartServiceOrderServiceDTO
{
    public function __construct(
        public string $serviceOrderServiceId,
        public ?int $startedUserId = null
    ) {}
}
