<?php

namespace App\Providers;

use App\Events\QueuePositionUpdated;
use App\Events\TicketCalled;
use App\Events\TicketCompleted;
use App\Events\TicketCreated;
use App\Events\TicketServing;
use App\Events\TicketStatusChanged;
use App\Listeners\BroadcastQueuePositionUpdated;
use App\Listeners\BroadcastTicketCalled;
use App\Listeners\BroadcastTicketCompleted;
use App\Listeners\BroadcastTicketCreated;
use App\Listeners\BroadcastTicketServing;
use App\Listeners\BroadcastTicketStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register ticket event listeners
        Event::listen(
            TicketCreated::class,
            BroadcastTicketCreated::class
        );

        Event::listen(
            TicketCalled::class,
            BroadcastTicketCalled::class
        );

        Event::listen(
            TicketServing::class,
            BroadcastTicketServing::class
        );

        Event::listen(
            TicketCompleted::class,
            BroadcastTicketCompleted::class
        );

        Event::listen(
            TicketStatusChanged::class,
            BroadcastTicketStatusChanged::class
        );

        Event::listen(
            QueuePositionUpdated::class,
            BroadcastQueuePositionUpdated::class
        );
    }
}
