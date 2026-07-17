<?php

namespace App\Providers;

use App\Domains\Bot\Services\BotOrchestratorService;
use App\Domains\Bot\Services\McpServerService;
use App\Domains\Bot\Services\OpenAiClientService;
use App\Domains\Bot\Services\ToolRegistryService;
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
        // Ticket event listeners are auto-discovered from app/Listeners.
    }
}
