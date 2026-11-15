<?php

namespace App\Lambda;

use App\Application\ServiceOrder\ProcessServiceOrderExecution;

/**
 * Lambda acionada pela fila FIFO service-order-execution (Services MS -> aplicação).
 */
class ServiceOrderExecutionHandler extends FifoSqsHandler
{
    public function __construct(private readonly ProcessServiceOrderExecution $processor) {}

    protected function process(array $payload): bool
    {
        return $this->processor->handle($payload);
    }
}
