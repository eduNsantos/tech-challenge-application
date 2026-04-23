<?php

declare(strict_types=1);

namespace App\Domain\ServiceOrder\Events;

use App\Domain\ServiceOrder\Entities\ServiceOrder;

class ServiceOrderQuoteSent
{
    public function __construct(
        public readonly ServiceOrder $serviceOrder
    ) {}
}
