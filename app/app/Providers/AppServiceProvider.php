<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // General API throttle: 120/min
        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip())));

        // Login brute-force guard
        RateLimiter::for('login', fn (Request $request) =>
            Limit::perMinute(5)->by(
                mb_strtolower((string) $request->input('email')) . '|' . $request->ip()
            ));

        RateLimiter::for('status', fn (Request $request) =>
            Limit::perMinute(30)->by((string) $request->ip()));

        // Safeguard: never serve production with debug on
        if ($this->app->environment('production') && config('app.debug')) {
            throw new \RuntimeException('APP_DEBUG must be false in production.');
        }
    }
}
