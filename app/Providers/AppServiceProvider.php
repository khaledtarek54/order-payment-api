<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // Telescope is a dev-only dependency; only load it locally so the app
        // never depends on it in production (where dev deps are absent).
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly on lazy loading / missing attributes outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Per-user (falls back to IP) throttling for general API traffic.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Tighter limit for credential endpoints (login / register).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip()));
    }
}
