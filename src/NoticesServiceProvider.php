<?php

namespace Dcplibrary\Notices;

use Dcplibrary\Notices\Commands\BackfillNotificationStatus;
use Dcplibrary\Notices\Commands\CheckShoutbombReportsCommand;
use Dcplibrary\Notices\Commands\ImportCommand;
use Dcplibrary\Notices\Commands\ImportShoutbombReports;
use Dcplibrary\Notices\Commands\InstallCommand;
use Dcplibrary\Notices\Commands\SeedDemoDataCommand;
use Dcplibrary\Notices\Commands\SyncAllCommand;
use Dcplibrary\Notices\Commands\TestConnections;
use Dcplibrary\Notices\Console\Commands\AggregateNotificationsCommand;
use Dcplibrary\Notices\Console\Commands\ImportPolarisCommand;
use Dcplibrary\Notices\Console\Commands\SyncNotificationsFromLogs;
use Dcplibrary\Notices\Database\Seeders\NoticesReferenceSeeder;
use Dcplibrary\Notices\Http\Livewire\SyncAndImport;
use Dcplibrary\Notices\Plugins\ShoutbombPlugin;
use Dcplibrary\Notices\Services\NoticeExportService;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Dcplibrary\Notices\Services\NotificationImportService;
use Dcplibrary\Notices\Services\PatronDeliveryPreferenceImporter;
use Dcplibrary\Notices\Services\PluginRegistry;
use Dcplibrary\Notices\Services\SettingsManager;
use Dcplibrary\Notices\Services\ShoutbombFailureReportParser;
use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Dcplibrary\Notices\Services\ShoutbombGraphApiService;
use Dcplibrary\Notices\Services\ShoutbombSubmissionParser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class NoticesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/notices.php',
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

        // PatronDeliveryPreferenceImporter needs the ShoutbombSubmissionParser and
        // ShoutbombFTPService; let the container resolve those dependencies.
        $this->app->singleton(PatronDeliveryPreferenceImporter::class, function ($app) {
            return new PatronDeliveryPreferenceImporter(
                $app->make(ShoutbombSubmissionParser::class),
                $app->make(ShoutbombFTPService::class)
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
            ImportShoutbombReports::class,
            TestConnections::class,
            SeedDemoDataCommand::class,
            BackfillNotificationStatus::class,
            CheckShoutbombReportsCommand::class,
            Commands\ImportShoutbombSubmissions::class,
            Commands\ImportPolarisPhoneNotices::class,
            Commands\ListShoutbombFiles::class,
            Commands\InspectDeliveryMethods::class,
            Commands\DiagnoseDataIssues::class,
            Commands\DiagnoseDashboardData::class,
            AggregateNotificationsCommand::class,
            ImportPolarisCommand::class,
            SyncNotificationsFromLogs::class,
        ]);

        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/notices.php' => config_path('notices.php'),
            ], 'notices-config');

            // Publish migrations (optional - package auto-loads them)
            $this->publishes([
                // Package migrations live in src/Database/Migrations
                __DIR__ . '/Database/Migrations' => database_path('migrations'),
            ], 'notices-migrations');

            // Publish views (if you create them later)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/notices'),
            ], 'notices-views');
        }

        // Load migrations automatically from the package's migration directory
        // Only if migrations haven't been published to avoid running twice
        $publishedMigrationPath = database_path('migrations/2025_01_01_000001_create_notification_logs_table.php');
        if (!file_exists($publishedMigrationPath)) {
            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        }

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'notices');

        // Register routes
        $this->registerRoutes();

        // Register scheduled tasks
        $this->registerScheduledTasks();

        // Register Livewire components used by the dashboard
        if (class_exists(Livewire::class)) {
            Livewire::component('sync-and-import', SyncAndImport::class);
        }

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
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });
        }

        // Register web (dashboard) routes if enabled
        if (config('notices.dashboard.enabled', true)) {
            Route::group([
                'prefix' => config('notices.dashboard.route_prefix', 'notices'),
                'middleware' => config('notices.dashboard.middleware', ['web', 'auth']),
                'as' => 'notices.',
            ], function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }
    }

    /**
     * Register scheduled tasks.
     *
     * Schedule is based on Polaris/Shoutbomb export times documented in:
     * - docs/shoutbomb/shoutbomb_process_explanation.md
     * - docs/shoutbomb/POLARIS_PHONE_NOTICES.md
     */
    protected function registerScheduledTasks(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $settings = $this->app->make(SettingsManager::class);

            // ═══════════════════════════════════════════════════════════════
            // MORNING IMPORTS (Data exported overnight and early morning)
            // ═══════════════════════════════════════════════════════════════

            // 5:30 AM - Import patron lists (voice_patrons.txt, text_patrons.txt)
            // → Exported at 4:00 AM (voice) and 5:00 AM (text)
            // → 30 min buffer for FTP upload
            if ($settings->get('scheduler.import_patron_lists_enabled', true)) {
                $schedule->command('notices:import-ftp-files --days=1 --import-patrons')
                    ->dailyAt('05:30')
                    ->withoutOverlapping()
                    ->description('Import patron delivery preference lists (voice + text)');
            }

            // 6:30 AM - Import Shoutbomb invalid phone reports via Microsoft Graph
            // 	Shoutbomb sends Daily Invalid Phone Report at ~6:01 AM
            // 	30 min buffer for email delivery
            if ($settings->get('scheduler.import_invalid_reports_enabled', true)) {
                $schedule->command('notices:import-email-reports --mark-read')
                    ->dailyAt('06:30')
                    ->withoutOverlapping()
                    ->description('Import opt-out and invalid phone number reports (Graph email)');
            }

            // 8:30 AM - Import morning notification exports + Polaris PhoneNotices
            // → Polaris exports: 8:00 AM (holds #1), 8:03 AM (renewals), 8:04 AM (overdues)
            // → Polaris PhoneNotices.csv: 8:04-8:05 AM
            // → 25-30 min buffer for export completion and FTP upload
            if ($settings->get('scheduler.import_morning_notifications_enabled', true)) {
                $schedule->command('notices:import-polaris --days=1')
                    ->dailyAt('08:30')
                    ->withoutOverlapping()
                    ->description('Import morning holds, renewals, overdues, and Polaris PhoneNotices');
            }

            // 9:30 AM - Import second hold export
            // → Hold Notifications Export #2 at 9:00 AM
            // → Captures holds processed overnight
            // → 30 min buffer
            if ($settings->get('scheduler.import_morning_holds_enabled', true)) {
                $schedule->command('notices:import-ftp-files --days=1')
                    ->dailyAt('09:30')
                    ->withoutOverlapping()
                    ->description('Import second morning hold notifications');
            }

            // ═══════════════════════════════════════════════════════════════
            // AFTERNOON IMPORTS (Midday and evening hold updates)
            // ═══════════════════════════════════════════════════════════════

            // 1:30 PM - Import afternoon hold export #3
            // → Hold Notifications Export #3 at 1:00 PM
            // → 30 min buffer
            if ($settings->get('scheduler.import_afternoon_holds_enabled', true)) {
                $schedule->command('notices:import-ftp-files --days=1')
                    ->dailyAt('13:30')
                    ->withoutOverlapping()
                    ->description('Import afternoon hold notifications');
            }

            // 4:30 PM - Import Shoutbomb voice failure reports via Microsoft Graph
            // 	Shoutbomb sends Daily Voice Failure Report at ~4:10 PM
            // 	20 min buffer for email delivery
            if ($settings->get('scheduler.import_voice_failures_enabled', true)) {
                $schedule->command('notices:import-email-reports --mark-read')
                    ->dailyAt('16:30')
                    ->withoutOverlapping()
                    ->description('Import voice call failure reports (Graph email)');
            }

            // 5:30 PM - Import evening hold export #4
            // → Hold Notifications Export #4 at 5:00 PM
            // → Final hold export of the day
            // → 30 min buffer
            if ($settings->get('scheduler.import_evening_holds_enabled', true)) {
                $schedule->command('notices:import-ftp-files --days=1')
                    ->dailyAt('17:30')
                    ->withoutOverlapping()
                    ->description('Import evening hold notifications');
            }

            // ═══════════════════════════════════════════════════════════════
            // END OF DAY PROCESSING (Aggregation and reporting)
            // ═══════════════════════════════════════════════════════════════

            // 9:45 PM - Sync master notifications from NotificationLog
            // Runs after all imports so NotificationLog is complete for the day
            if ($settings->get('scheduler.sync_from_logs_enabled', true)) {
                $schedule->command('notices:sync-from-logs --days=1')
                    ->dailyAt('21:45')
                    ->withoutOverlapping()
                    ->description('Project NotificationLog rows into master notifications and events');
            }

            // 10:00 PM - Daily aggregation of all notification data
            // Runs after all imports are complete and notifications have been projected
            // Aggregates data for dashboard and reporting
            if ($settings->get('scheduler.aggregation_enabled', true)) {
                $schedule->command('notices:aggregate --days=1')
                    ->dailyAt('22:00')
                    ->withoutOverlapping()
                    ->description('Aggregate yesterday\'s notification data for reporting');
            }

            // ═══════════════════════════════════════════════════════════════
            // MONTHLY TASKS
            // ═══════════════════════════════════════════════════════════════

            // 2nd of each month at 2:00 PM - Import monthly statistics report via Microsoft Graph
            // 	Shoutbomb sends Monthly Statistics Report on 1st of month at ~1:14 PM
            // 	Import next day to ensure email has arrived
            if ($settings->get('scheduler.import_monthly_stats_enabled', true)) {
                $schedule->command('notices:import-email-reports --mark-read')
                    ->monthlyOn(2, '14:00')
                    ->withoutOverlapping()
                    ->description('Import Shoutbomb monthly statistics report (Graph email)');
            }

            // (Former legacy unified import schedule removed; use granular commands instead.)
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
