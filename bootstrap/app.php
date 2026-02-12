<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // For this project we use Sanctum primarily with personal access tokens
        // (Authorization: Bearer ...) for API auth. We do NOT need Sanctum's
        // stateful SPA middleware, which wraps the web stack (sessions + CSRF)
        // around API routes and can cause 419 CSRF errors.
        //
        // So we intentionally do NOT call $middleware->statefulApi() here.
        //
        // If in future you want cookie-based SPA auth, you can re-enable:
        // $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom exception handling is done in App\Exceptions\Handler
    })->create();
