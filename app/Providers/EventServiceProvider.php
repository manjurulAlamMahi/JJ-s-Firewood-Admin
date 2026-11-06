<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Events
use App\Events\OrderPlaced;
use App\Events\OrderCancelled;
use App\Events\PaymentRefunded;
use App\Events\OrderDeliveryDateUpdated;
use App\Events\OrderDelivered;

// Listeners
use App\Listeners\SendOrderNotification;
use App\Listeners\SendOrderCancelledNotification;
use App\Listeners\HandlePaymentRefund;
use App\Listeners\SendOrderDeliveryDateUpdatedNotification;
use App\Listeners\SendOrderDeliveredNotification;
use App\Listeners\SendDeliveryFollowUp;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderPlaced::class => [
            SendOrderNotification::class,
        ],
        OrderCancelled::class => [
            SendOrderCancelledNotification::class,
        ],
        PaymentRefunded::class => [
            HandlePaymentRefund::class,
        ],
        OrderDeliveryDateUpdated::class => [
            SendOrderDeliveryDateUpdatedNotification::class,
        ],
        OrderDelivered::class => [
            SendOrderDeliveredNotification::class,
            SendDeliveryFollowUp::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}