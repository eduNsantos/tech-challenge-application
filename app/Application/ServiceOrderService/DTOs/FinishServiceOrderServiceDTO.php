<?php

namespace App\Application\ServiceOrderService\DTOs;

class FinishServiceOrderServiceDTO
{
    public function __construct(
        public string $serviceOrderServiceId,
        public ?int $finishedUserId = null
    ) {}
}
