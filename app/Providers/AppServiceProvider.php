<?php

namespace App\Providers;

use App\Domains\Bot\Services\BotOrchestratorService;
use App\Domains\Bot\Services\McpServerService;
use App\Domains\Bot\Services\OpenAiClientService;
use App\Domains\Bot\Services\ToolRegistryService;
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
use App\Listeners\SendTicketCreatedSms;
use App\Listeners\SendTicketCompletedSms;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BotOrchestratorService::class, fn () => new BotOrchestratorService());
        $this->app->singleton(ToolRegistryService::class, fn () => new ToolRegistryService());
        $this->app->singleton(McpServerService::class, fn () => new McpServerService());
        $this->app->singleton(OpenAiClientService::class, fn () => new OpenAiClientService());
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

        // Register SMS notification listeners
        Event::listen(
            TicketCreated::class,
            SendTicketCreatedSms::class
        );

        Event::listen(
            TicketCompleted::class,
            SendTicketCompletedSms::class
        );
    }
}
