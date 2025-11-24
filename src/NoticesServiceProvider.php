<?php

namespace Dcplibrary\Notices;

use Dcplibrary\Notices\Commands\BackfillNotificationStatus;
use Dcplibrary\Notices\Commands\CheckShoutbombReportsCommand;
use Dcplibrary\Notices\Commands\ImportCommand;
use Dcplibrary\Notices\Commands\ImportEmailReports;
use Dcplibrary\Notices\Commands\ImportNotifications;
use Dcplibrary\Notices\Commands\ImportShoutbombReports;
use Dcplibrary\Notices\Commands\InstallCommand;
use Dcplibrary\Notices\Commands\SeedDemoDataCommand;
use Dcplibrary\Notices\Commands\SyncAllCommand;
use Dcplibrary\Notices\Commands\TestConnections;
use Dcplibrary\Notices\Plugins\ShoutbombPlugin;
use Dcplibrary\Notices\Services\NoticeExportService;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Services\NotificationImportService;
use Dcplibrary\Notices\Services\PluginRegistry;
use Dcplibrary\Notices\Services\SettingsManager;
use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Dcplibrary\Notices\Services\ShoutbombGraphApiService;
use Dcplibrary\Notices\Services\ShoutbombFailureReportParser;
use Dcplibrary\Notices\Database\Seeders\NoticesReferenceSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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

        // Register NoticeExportService as a singleton
        $this->app->singleton(NoticeExportService::class, function ($app) {
            return new NoticeExportService($app->make(NoticeVerificationService::class));
        });

        // Register NotificationImportService as a singleton
        $this->app->singleton(NotificationImportService::class, function ($app) {
            return new NotificationImportService($app->make(ShoutbombFTPService::class));
        });

        // Register Shoutbomb Graph API service (for Outlook/Graph ingestion)
        $this->app->singleton(ShoutbombGraphApiService::class, function ($app) {
            $graphConfig = config('notices.integrations.shoutbomb_reports.graph', []);
            return new ShoutbombGraphApiService($graphConfig);
        });

        // Register Shoutbomb failure report parser
        $this->app->singleton(ShoutbombFailureReportParser::class, function ($app) {
            $parsingConfig = config('notices.integrations.shoutbomb_reports.parsing', []);
            return new ShoutbombFailureReportParser($parsingConfig);
        });

        $this->app->singleton(PatronDeliveryPreferenceImporter::class, function ($app) {
            return new PatronDeliveryPreferenceImporter(
                config('notices.shoutbomb.ftp.host'),
                config('notices.shoutbomb.ftp.port'),
                config('notices.shoutbomb.ftp.username'),
                config('notices.shoutbomb.ftp.password'),
                config('notices.shoutbomb.ftp.patron_path', '/outgoing'),
                config('notices.shoutbomb.ftp.passive', true)
            );
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
        // Register commands (must be outside runningInConsole so Artisan::call() from web works)
        $this->commands([
            InstallCommand::class,
            SyncAllCommand::class,
            ImportCommand::class, // Simplified unified import command
            ImportNotifications::class,
            ImportShoutbombReports::class,
            ImportEmailReports::class,
            TestConnections::class,
            SeedDemoDataCommand::class,
            BackfillNotificationStatus::class,
            Commands\CheckShoutbombReportsCommand::class,
            Commands\ImportShoutbombSubmissions::class,
            Commands\ImportPolarisPhoneNotices::class,
            Commands\ListShoutbombFiles::class,
            Commands\InspectDeliveryMethods::class,
            Commands\DiagnoseDataIssues::class,
            Commands\DiagnoseDashboardData::class,
            Commands\SyncShoutbombToLogs::class,
            Console\Commands\DiagnoseDashboardDataCommand::class,
            Console\Commands\DiagnosePatronDataCommand::class,
            Console\Commands\ImportFTPFiles::class,
            Console\Commands\ImportPolarisCommand::class,
            Console\Commands\ImportShoutbombCommand::class,
            Console\Commands\AggregateNotificationsCommand::class,
            CheckShoutbombReportsCommand::class,
        ]);

        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/notices.php' => config_path('notices.php'),
            ], 'notices-config');

            // Publish migrations (optional - package auto-loads them)
            $this->publishes([
                // Package migrations live in src/Database/Migrations
                __DIR__.'/Database/Migrations' => database_path('migrations'),
            ], 'notices-migrations');

            // Publish views (if you create them later)
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/notices'),
            ], 'notices-views');
        }

        // Load migrations automatically from the package's migration directory
        // Only if migrations haven't been published to avoid running twice
        $publishedMigrationPath = database_path('migrations/2025_01_01_000001_create_notification_logs_table.php');
        if (!file_exists($publishedMigrationPath)) {
            $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        }

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'notices');

        // Register routes
        $this->registerRoutes();

        // Register scheduled tasks
        $this->registerScheduledTasks();

        // Auto-seed reference data when running plain `db:seed` (no --class) to include lookup tables
        if ($this->app->runningInConsole() && $this->shouldAutoSeedReference()) {
            // Prevent double-run in case of nested calls
            if (!defined('NOTICES_REFERENCE_SEEDED')) {
                define('NOTICES_REFERENCE_SEEDED', true);
                $seeder = new NoticesReferenceSeeder();
                $seeder->setContainer($this->app);
                $seeder->run();
            }
        }
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
     * Register scheduled tasks.
     */
    protected function registerScheduledTasks(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $settings = $this->app->make(SettingsManager::class);

            // Import all notification data daily using the unified import command
            // This includes: patron lists, phone notices, notification exports,
            // enrichment, and aggregation - all in one step
            if ($settings->get('scheduler.import_enabled', true)) {
                $time = $settings->get('scheduler.import_time', '09:00');
                $schedule->command('notices:import --days=1')
                    ->dailyAt($time)
                    ->withoutOverlapping();
            }

            // Import email reports daily at 9:30 AM
            if ($settings->get('scheduler.import_email_enabled', true)) {
                $time = $settings->get('scheduler.import_email_time', '09:30');
                $schedule->command('notices:import-email-reports --mark-read')
                    ->dailyAt($time)
                    ->withoutOverlapping();
            }
        });
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
    /**
     * Determine if we should auto-seed reference data on `db:seed`.
     */
    protected function shouldAutoSeedReference(): bool
    {
        $argv = $_SERVER['argv'] ?? [];
        if (!is_array($argv)) {
            return false;
        }

        // Only auto-seed on plain `db:seed`.
        // For `migrate:fresh --seed`, Laravel will invoke `db:seed` after
        // migrations have successfully run, so this condition will still be met
        // but only once tables like `delivery_methods` exist.
        $isDbSeed = in_array('db:seed', $argv, true);

        $hasClass = collect($argv)->contains(function ($arg) {
            return str_starts_with($arg, '--class') || $arg === '--class';
        });

        return $isDbSeed && !$hasClass;
    }
}
