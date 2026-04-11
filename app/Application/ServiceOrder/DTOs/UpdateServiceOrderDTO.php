<?php

namespace App\Application\ServiceOrder\DTOs;

class UpdateServiceOrderDTO
{
    public function __construct(
        public string $id,
        public ?array $services,
        public ?array $parts,
        public ?string $vehicleBrand,
        public ?string $vehicleModel,
        public ?int $vehicleYear,
        public ?string $vehiclePlate,
        public ?string $status,
        public ?bool $sendQuote,
        public ?bool $approveQuote
    ) {}
}
