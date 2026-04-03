<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
        \App\Http\Middleware\NoCacheHeaders::class,
        ]);

        $middleware->api(prepend: [
            // \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\BypassAuth::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
