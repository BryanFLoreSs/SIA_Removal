<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        // The frontend uses explicit Sanctum bearer tokens; no cookie/CSRF session flow is needed.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default JSON exception rendering is used for API requests.
    })->create();
