<?php

namespace App\Providers;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Infrastructure\Notifications\Services\NotificationService;
use App\Support\Observability\NewRelicTelemetry;
use App\Support\Observability\TelemetryInterface;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
