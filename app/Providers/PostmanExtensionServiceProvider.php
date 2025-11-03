<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Config\Repository as Config;
use YasinTgh\LaravelPostman\Collections\Builder;
use YasinTgh\LaravelPostman\Services\NameGenerator;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;
use App\Services\ExtendedRouteGrouper;
use App\Services\QueryParameterExtractor;

class PostmanExtensionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Override the Builder binding to use our custom RouteGrouper
        $this->app->singleton(Builder::class, function ($app) {
            $config = $app->make(Config::class)->get('postman', []);

            return new Builder(
                new ExtendedRouteGrouper(
                    $config['structure']['folders']['strategy'] ?? 'prefix',
                    $config,
                    $app->make(NameGenerator::class),
                    $app->make(RequestBodyGenerator::class),
                    $app->make(QueryParameterExtractor::class)
                ),
                $config
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
