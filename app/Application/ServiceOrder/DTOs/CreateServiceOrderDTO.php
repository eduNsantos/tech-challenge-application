<?php

namespace App\Application\ServiceOrder\DTOs;

use App\Models\User;

class CreateServiceOrderDTO
{
    public function __construct(
        public User $user,
        public string $vehicleId,
        public array $services,
        public array $items = [],
        public bool $sendQuote = true
    ) {}
}
