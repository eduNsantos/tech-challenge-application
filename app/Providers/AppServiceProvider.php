<?php

namespace App\Providers;

use App\Domain\Notification\Interfaces\NotificationServiceInterface;
use App\Infrastructure\Notifications\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(
            NotificationServiceInterface::class,
            NotificationService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
