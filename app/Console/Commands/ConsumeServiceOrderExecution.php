<?php

namespace App\Console\Commands;

use App\Application\ServiceOrder\ProcessServiceOrderExecution;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Throwable;

class ConsumeServiceOrderExecution extends Command
{
    protected $signature = 'sqs:consume-service-execution {--once : Process a single poll and exit}';

    protected $description = 'Consume service execution events from the SQS FIFO queue';

    public function handle(ProcessServiceOrderExecution $processor): int
    {
        $url = config('services_ms.sqs.service_order_execution_url');

        if (! $url) {
            $this->error('SQS_SERVICE_ORDER_EXECUTION_URL is not configured.');

            return self::FAILURE;
        }

        $client = $this->client();

        do {
            $result = $client->receiveMessage([
                'QueueUrl' => $url,
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds' => 20,
            ]);

            foreach ($result->get('Messages') ?? [] as $message) {
                $this->process($client, $url, $processor, $message);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function process(SqsClient $client, string $url, ProcessServiceOrderExecution $processor, array $message): void
    {
        try {
            $payload = json_decode($message['Body'], true, 512, JSON_THROW_ON_ERROR);
            $processed = $processor->handle($payload);
            $this->info(($processed ? 'Applied ' : 'Skipped ').($payload['event_id'] ?? ''));
        } catch (JsonException|InvalidArgumentException $e) {
            // Mensagem inválida nunca terá sucesso: não apaga, e o redrive a envia para a DLQ.
            logger()->error('Invalid service order execution message', ['error' => $e->getMessage(), 'message_id' => $message['MessageId'] ?? null]);

            return;
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $client->deleteMessage([
            'QueueUrl' => $url,
            'ReceiptHandle' => $message['ReceiptHandle'],
        ]);
    }

    private function client(): SqsClient
    {
        $config = [
            'version' => 'latest',
            'region' => config('services_ms.sqs.region'),
        ];

        if ($endpoint = config('services_ms.sqs.endpoint')) {
            $config['endpoint'] = $endpoint;
        }

        return new SqsClient($config);
    }
}
