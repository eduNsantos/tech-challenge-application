<?php

namespace App\Providers;

use App\Application\Notification\Handlers\NotifyCustomerOnPaymentLinkReady;
use App\Application\Notification\Handlers\NotifyCustomerOnServiceOrderFinalized;
use App\Application\Notification\Handlers\NotifyMechanicsOnServiceOrderCreated;
use App\Application\Notification\Handlers\SendServiceOrderStatusNotification;
use App\Application\Notification\Handlers\SendWelcomeNotification;
use App\Domain\Customer\Events\CustomerCreated;
use App\Domain\ServiceOrder\Events\ServiceOrderCreated;
use App\Domain\ServiceOrder\Events\ServiceOrderPaymentLinkReady;
use App\Domain\ServiceOrder\Events\ServiceOrderStatusChanged;
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
        Event::listen(
            CustomerCreated::class, 
            SendWelcomeNotification::class
        );
        Event::listen(
            ServiceOrderStatusChanged::class,
            SendServiceOrderStatusNotification::class
        );

        Event::listen(
            ServiceOrderCreated::class,
            NotifyMechanicsOnServiceOrderCreated::class
        );

        // O orçamento só é enviado ao cliente quando o payment-service devolve o link de pagamento
        Event::listen(
            ServiceOrderPaymentLinkReady::class,
            NotifyCustomerOnPaymentLinkReady::class
        );

        Event::listen(
            ServiceOrderStatusChanged::class,
            NotifyCustomerOnServiceOrderFinalized::class
        );
    }
}
