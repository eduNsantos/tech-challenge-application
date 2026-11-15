<?php

namespace App\Providers;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Infrastructure\Notifications\Services\NotificationService;
use App\Support\Observability\NewRelicTelemetry;
use App\Support\Observability\TelemetryInterface;
use Aws\Sqs\SqsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            NotificationServiceInterface::class,
            NotificationService::class
        );

        $this->app->bind(TelemetryInterface::class, NewRelicTelemetry::class);

        $this->app->singleton(SqsClient::class, function (): SqsClient {
            $config = ['version' => 'latest', 'region' => config('services_ms.sqs.region')];

            if ($endpoint = config('services_ms.sqs.endpoint')) {
                $config['endpoint'] = $endpoint;
            }

            return new SqsClient($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
