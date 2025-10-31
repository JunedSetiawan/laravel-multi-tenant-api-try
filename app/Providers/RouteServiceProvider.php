<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Central Routes (Management Tenant) - NO tenant middleware
            Route::middleware('api')
                ->prefix('api/central')
                ->group(base_path('routes/central.php'));

            // Tenant Routes (API Key Based) - WITH tenant middleware FIRST
            Route::middleware(['tenant', 'api'])
                ->prefix('api')
                ->group(base_path('routes/tenant.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
