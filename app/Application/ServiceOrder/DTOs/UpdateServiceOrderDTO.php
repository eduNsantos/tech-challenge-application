<?php

namespace App\Application\ServiceOrder\DTOs;

class UpdateServiceOrderDTO
{
    public function __construct(
        public string $id,
        public ?array $services,
        public ?array $parts,
        public ?string $vehicleId,
        public ?string $status,
        public ?bool $sendQuote,
        public ?bool $approveQuote
    ) {}
}
