<?php

namespace App\Providers;

use App\Application\Notification\Handlers\SendWelcomeNotification;
use App\Domain\Customer\Events\CustomerCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Event::listen(CustomerCreated::class, SendWelcomeNotification::class);
    }
}
