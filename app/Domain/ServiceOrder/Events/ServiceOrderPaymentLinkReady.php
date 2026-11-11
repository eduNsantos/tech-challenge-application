<?php

declare(strict_types=1);

namespace App\Domain\ServiceOrder\Events;

use App\Domain\ServiceOrder\Entities\ServiceOrder;

class ServiceOrderPaymentLinkReady
{
    public function __construct(
        public readonly ServiceOrder $serviceOrder
    ) {}
}
