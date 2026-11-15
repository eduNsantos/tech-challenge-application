<?php

namespace App\Lambda;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Base dos consumers de filas FIFO acionados pelo event source mapping do SQS.
 *
 * Mensagens com falha voltam para a fila via batchItemFailures (ReportBatchItemFailures);
 * após o maxReceiveCount da fila, o redrive as envia para a DLQ. Em filas FIFO, a AWS
 * exige que o processamento pare na primeira falha e que as mensagens restantes do lote
 * também sejam devolvidas, para não quebrar a ordem do grupo.
 */
abstract class FifoSqsHandler extends SqsHandler
{
    /**
     * @return bool true quando a mensagem alterou o estado; false quando foi ignorada (duplicada/antiga).
     *
     * @throws InvalidArgumentException quando a mensagem é inválida.
     */
    abstract protected function process(array $payload): bool;

    public function handleSqs(SqsEvent $event, Context $context): void
    {
        $failed = false;

        foreach ($event->getRecords() as $record) {
            if ($failed) {
                $this->markAsFailed($record);

                continue;
            }

            try {
                $payload = json_decode($record->getBody(), true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($payload)) {
                    throw new InvalidArgumentException('The message body must be a JSON object.');
                }

                $processed = $this->process($payload);

                logger()->info(($processed ? 'Processed ' : 'Skipped ').static::class, [
                    'event_id' => $payload['event_id'] ?? null,
                    'message_id' => $record->getMessageId(),
                ]);
            } catch (JsonException|InvalidArgumentException $e) {
                // Mensagem inválida nunca terá sucesso: volta para a fila e o redrive a envia para a DLQ.
                logger()->error('Invalid SQS message', [
                    'handler' => static::class,
                    'error' => $e->getMessage(),
                    'message_id' => $record->getMessageId(),
                ]);
                $failed = true;
                $this->markAsFailed($record);
            } catch (Throwable $e) {
                report($e);
                $failed = true;
                $this->markAsFailed($record);
            }
        }
    }
}
