<?php

namespace Dcplibrary\PolarisNotifications;

use Dcplibrary\PolarisNotifications\Commands\ImportPolarisNotifications;
use Dcplibrary\PolarisNotifications\Commands\ImportShoutbombReports;
use Dcplibrary\PolarisNotifications\Commands\AggregateNotifications;
use Dcplibrary\PolarisNotifications\Commands\TestConnections;
use Dcplibrary\PolarisNotifications\Commands\SeedDemoDataCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class PolarisNotificationsServiceProvider extends ServiceProvider
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
                ImportPolarisNotifications::class,
                ImportShoutbombReports::class,
                AggregateNotifications::class,
                TestConnections::class,
                SeedDemoDataCommand::class,
            ]);
        }

        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load routes (if you create them)
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load views (if you create them)
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'notifications');
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
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'notifications',
        ];
    }
}
