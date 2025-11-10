<?php

namespace Dcplibrary\Notices;

use Dcplibrary\Notices\Commands\ImportNotifications;
use Dcplibrary\Notices\Commands\ImportShoutbombReports;
use Dcplibrary\Notices\Commands\ImportEmailReports;
use Dcplibrary\Notices\Commands\AggregateNotifications;
use Dcplibrary\Notices\Commands\TestConnections;
use Dcplibrary\Notices\Commands\SeedDemoDataCommand;
use Dcplibrary\Notices\Services\SettingsManager;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Services\PluginRegistry;
use Dcplibrary\Notices\Plugins\ShoutbombPlugin;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

class NoticesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__.'/../config/notices.php',
            'notices'
        );

        // Register the Polaris database connection
        $this->registerPolarisConnection();

        // Register SettingsManager as a singleton
        $this->app->singleton(SettingsManager::class, function ($app) {
            return new SettingsManager();
        });

        // Register PluginRegistry as a singleton
        $this->app->singleton(PluginRegistry::class, function ($app) {
            return $this->createPluginRegistry();
        });

        // Register NoticeVerificationService as a singleton with plugin support
        $this->app->singleton(NoticeVerificationService::class, function ($app) {
            $service = new NoticeVerificationService();
            $service->setPluginRegistry($app->make(PluginRegistry::class));
            return $service;
        });
    }

    /**
     * Create and configure the plugin registry.
     */
    protected function createPluginRegistry(): PluginRegistry
    {
        $registry = new PluginRegistry();

        // Register Shoutbomb plugin
        $registry->register(new ShoutbombPlugin());

        // Register additional plugins here as they are created
        // $registry->register(new EmailPlugin());
        // $registry->register(new SmsDirectPlugin());

        return $registry;
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/notices.php' => config_path('notices.php'),
            ], 'notices-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'notices-migrations');

            // Publish views (if you create them later)
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/notices'),
            ], 'notices-views');

            // Register commands
            $this->commands([
                ImportNotifications::class,
                ImportShoutbombReports::class,
                ImportEmailReports::class,
                AggregateNotifications::class,
                TestConnections::class,
                SeedDemoDataCommand::class,
                Commands\ImportShoutbombSubmissions::class,
                Commands\ImportShoutbombPhoneNotices::class,
            ]);
        }

        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'notices');

        // Register routes
        $this->registerRoutes();
    }

    /**
     * Register the Polaris database connection.
     */
    protected function registerPolarisConnection(): void
    {
        $config = config('notices.polaris_connection');

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
        if (config('notices.api.enabled', true)) {
            Route::group([
                'prefix' => config('notices.api.route_prefix', 'api/notices'),
                'middleware' => config('notices.api.middleware', ['api', 'auth:sanctum']),
                'as' => 'notices.api.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        // Register web (dashboard) routes if enabled
        if (config('notices.dashboard.enabled', true)) {
            Route::group([
                'prefix' => config('notices.dashboard.route_prefix', 'notices'),
                'middleware' => config('notices.dashboard.middleware', ['web', 'auth']),
                'as' => 'notices.',
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
            'notices',
        ];
    }
}
