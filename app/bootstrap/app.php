<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\RoleMiddleware::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The submitted data was invalid. Please check the errors and try again.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Clean 429 for API/JSON callers (preserves Retry-After / rate-limit headers).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down and try again shortly.',
                ], 429)->withHeaders($e->getHeaders());
            }
        });

        // Catch-all for API: never leak internals to API/JSON callers in production
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->wantsJson() || $request->is('api/*')) || config('app.debug')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'message' => 'Server error. Please try again later.',
            ], $status);
        });
    })->create();
