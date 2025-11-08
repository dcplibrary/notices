<?php

namespace Dcplibrary\Notifications;

use Dcplibrary\Notifications\Commands\ImportNotifications;
use Dcplibrary\Notifications\Commands\ImportShoutbombReports;
use Dcplibrary\Notifications\Commands\AggregateNotifications;
use Dcplibrary\Notifications\Commands\TestConnections;
use Dcplibrary\Notifications\Commands\SeedDemoDataCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

class NotificationsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifications.php',
            'notifications'
        );

        // Register the Polaris database connection
        $this->registerPolarisConnection();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/notifications.php' => config_path('notifications.php'),
            ], 'notifications-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'notifications-migrations');

            // Publish views (if you create them later)
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/notifications'),
            ], 'notifications-views');

            // Register commands
            $this->commands([
                ImportNotifications::class,
                ImportShoutbombReports::class,
                AggregateNotifications::class,
                TestConnections::class,
                SeedDemoDataCommand::class,
            ]);
        }

        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'notifications');

        // Register routes
        $this->registerRoutes();
    }

    /**
     * Register the Polaris database connection.
     */
    protected function registerPolarisConnection(): void
    {
        $config = config('notifications.polaris_connection');

        if ($config) {
            Config::set('database.connections.polaris', $config);
        }
    }

    /**
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        // Register API routes if enabled
        if (config('notifications.api.enabled', true)) {
            Route::group([
                'prefix' => config('notifications.api.route_prefix', 'api/notifications'),
                'middleware' => config('notifications.api.middleware', ['api', 'auth:sanctum']),
                'as' => 'notifications.api.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        // Register web (dashboard) routes if enabled
        if (config('notifications.dashboard.enabled', true)) {
            Route::group([
                'prefix' => config('notifications.dashboard.route_prefix', 'notifications'),
                'middleware' => config('notifications.dashboard.middleware', ['web', 'auth']),
                'as' => 'notifications.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'notifications',
        ];
    }
}
