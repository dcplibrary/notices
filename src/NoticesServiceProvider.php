<?php

namespace Dcplibrary\Notices;

use Dcplibrary\Notices\Contracts\EmailIngestionInterface;
use Dcplibrary\Notices\Contracts\NotificationFailureDetectorInterface;
use Dcplibrary\Notices\Contracts\NotificationImporterInterface;
use Dcplibrary\Notices\Contracts\NotificationReconcilerInterface;
use Dcplibrary\Notices\Services\FtpNotificationImporter;
use Dcplibrary\Notices\Services\GraphEmailIngestionService;
use Dcplibrary\Notices\Services\Import\FtpImportService;
use Dcplibrary\Notices\Services\LookupLabelService;
use Dcplibrary\Notices\Services\NotificationFailureDetector;
use Dcplibrary\Notices\Services\NotificationReconciler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class NoticesServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * This wires the high-level notices contracts (importer,
     * email ingestion, reconciliation, failure detection) to
     * their concrete implementations and exposes a singleton
     * NoticesManager facade entry point for host applications.
     */
    public function register(): void
    {
        // Prevent Livewire auto-discovery errors in Laravel 12
        // Laravel 12 tries to auto-detect Livewire when it sees Alpine.js attributes
        // If Livewire is not installed, this causes "Class not found" errors
        if (!class_exists(\Livewire\Livewire::class)) {
            // Set config early to prevent auto-discovery
            try {
                $config = $this->app->make('config');
                if (method_exists($config, 'set')) {
                    $config->set('livewire.disable_auto_discovery', true);
                }
            } catch (\Exception $e) {
                // Config might not be available yet, ignore
            }
        }

        // Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/notices.php', 'notices');

        $this->registerCoreServiceBindings();
        $this->registerUiServiceBindings();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // x-dcpl::layouts.staff emits @livewireStyles / @livewireScripts; stubs when Livewire is absent.
        $this->registerLivewireBladeFallbacks();

        // Prevent Livewire Blade extension errors if Livewire is not installed
        // This must be done in boot() after the app is fully booted
        if (!class_exists(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::class)) {
            // Try to prevent Livewire from being auto-discovered
            try {
                $config = $this->app->make('config');
                $config->set('livewire.disable_auto_discovery', true);
            } catch (\Exception $e) {
                // Ignore if config is not available
            }
        }

        // Allow host app to publish the notices config file
        $this->publishes([
            __DIR__ . '/../config/notices.php' => config_path('notices.php'),
        ], 'config');

        // Some Laravel 11/12 host apps do not register Sanctum ability aliases by
        // default. Our API routes use ability/abilities middleware strings, so
        // register aliases here to keep package endpoints self-contained.
        $this->registerSanctumAbilityAliases();

        // Load package migrations so the host app can run them directly.
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // Serve compiled package CSS directly from resources/dist — no vendor:publish needed.
        // Cache-busted with MD5 hash of the file, matching the requests package pattern.
        $this->registerCssRoute();
        $this->registerHelpRoute();
        $this->registerHelpImageRoute();

        // Load package routes and views.
        // Route manifests (routes/web.php, routes/api.php) fan out to module files.
        $this->loadPackageRoutes();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'notices');

        // Register Blade component namespace for x-notices:: components.
        // Also registers anonymous Blade components from resources/views/components/.
        Blade::componentNamespace('Dcplibrary\Notices\View\Components', 'notices');
        Blade::anonymousComponentNamespace('notices::components', 'notices');

        // Shared dcpl:: Blade namespace (logo, staff layout pieces, etc.) — mirrors dcplibrary/requests.
        $this->registerDcplUiViews();

        // Register Artisan commands
        // NOTE: Commands are registered for both CLI and web contexts to support Artisan::call().
        $this->commands([
            Console\ImportCommand::class,
            Console\ImportFtpExportsCommand::class,
            Console\ImportEmailCommand::class,
            Console\ImportConfirmationsCommand::class,
            Console\SyncPolarisLookupTablesCommand::class,
            Console\ProcessOrphanedNotificationsCommand::class,
            Console\SeedCommand::class,
            Console\IntegrityCheckCommand::class,
            Console\CleanupImportLogsCommand::class,
        ]);

        // Allow publishing seeders (optional - seeders can be run directly from package)
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Database/Seeders' => database_path('seeders'),
            ], 'seeders');
        }

        // Register Blade directives for lookup labels
        $this->registerBladeDirectives();

        // Register scheduled tasks
        $this->registerScheduledTasks();

        // Register query performance logging
        $this->registerQueryPerformanceLogging();

        $this->registerDcplUiCssRoute();
        $this->shareDcplUiCssVersion();
    }

    /**
     * Register Sanctum ability middleware aliases when available.
     */
    protected function registerSanctumAbilityAliases(): void
    {
        if (!$this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('abilities', CheckAbilities::class);
        $router->aliasMiddleware('ability', CheckForAnyAbility::class);

        // Register notices user role middleware
        $router->aliasMiddleware('notices.user-role', Http\Middleware\RedirectUserRole::class);
    }

    /**
     * Register core import/reconciliation services.
     */
    protected function registerCoreServiceBindings(): void
    {
        $this->app->bind(NotificationImporterInterface::class, function ($app) {
            return new FtpNotificationImporter($app->make(FtpImportService::class));
        });
        $this->app->bind(EmailIngestionInterface::class, GraphEmailIngestionService::class);
        $this->app->bind(NotificationReconcilerInterface::class, NotificationReconciler::class);
        $this->app->bind(NotificationFailureDetectorInterface::class, NotificationFailureDetector::class);

        // Core manager service that coordinates imports, reconciliation, and failure detection.
        $this->app->singleton(NoticesManager::class, function ($app) {
            return new NoticesManager(
                $app->make(NotificationImporterInterface::class),
                $app->make(EmailIngestionInterface::class),
                $app->make(NotificationReconcilerInterface::class),
                $app->make(NotificationFailureDetectorInterface::class),
            );
        });

        // Facade accessor binding
        $this->app->alias(NoticesManager::class, 'dcplibrary.notices');
    }

    /**
     * Register dashboard/query services used by notices UI modules.
     */
    protected function registerUiServiceBindings(): void
    {
        $this->app->singleton(Services\NotificationService::class);
        $this->app->singleton(Services\ConfirmationQueryService::class);
        $this->app->singleton(Services\NotificationMetricsQueryService::class);
        $this->app->singleton(Services\NotificationDiagnosticsQueryService::class);
        $this->app->singleton(Services\FailedContactResponseBuilder::class);
        $this->app->singleton(Services\NotificationQueryService::class);
        $this->app->singleton(Services\IssueQueryService::class);
        $this->app->singleton(Services\FailedContactQueryService::class);
        $this->app->singleton(Services\IssueResolutionService::class);
        $this->app->singleton(Services\OrphanedNotificationQueryService::class);
        $this->app->singleton(Services\ImportStatusService::class);
        $this->app->singleton(LookupLabelService::class);
    }

    /**
     * Register a route that serves the compiled notices.css directly from
     * resources/dist inside the package — no vendor:publish required.
     *
     * The cache-busting version string is an 8-character MD5 of the file,
     * mirroring the pattern used in dcplibrary/requests.
     */
    protected function registerCssRoute(): void
    {
        $cssPath = __DIR__ . '/../resources/dist/notices.css';

        \Illuminate\Support\Facades\Route::get('notices/assets/css', function () use ($cssPath) {
            $cacheControl = app()->environment('production')
                ? 'public, max-age=31536000'
                : 'no-cache, private, must-revalidate';

            return response(file_get_contents($cssPath), 200)
                ->header('Content-Type', 'text/css')
                ->header('Cache-Control', $cacheControl)
            ;
        })->name('notices.assets.css');

        // Share cache-busting version with all notices views.
        $version = file_exists($cssPath) ? substr(md5_file($cssPath), 0, 8) : 'dev';
        View::share('noticesCssVersion', $version);
    }

    /**
     * Register a route that serves the Viewer help page HTML directly from
     * resources/dist/notices-viewer-help.html — no auth required, no vendor:publish.
     */
    protected function registerHelpRoute(): void
    {
        $htmlPath = __DIR__ . '/../resources/dist/notices-viewer-help.html';

        \Illuminate\Support\Facades\Route::get('notices/viewer-help', function () use ($htmlPath) {
            if (!file_exists($htmlPath)) {
                abort(404);
            }

            return response(file_get_contents($htmlPath), 200)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Cache-Control', 'no-cache, private')
            ;
        })->name('notices.viewer-help');
    }

    /**
     * Register a route that serves help page images from resources/dist/img/help/
     * No vendor:publish required — served directly from the package.
     */
    protected function registerHelpImageRoute(): void
    {
        $imgBase = __DIR__ . '/../resources/dist/img/help';

        \Illuminate\Support\Facades\Route::get('notices/assets/img/help/{filename}', function ($filename) use ($imgBase) {
            // Sanitize: only allow safe filenames (letters, numbers, dash, underscore, dot)
            if (!preg_match('/^[a-zA-Z0-9\-_]+\.(jpg|jpeg|png|gif|webp)$/', $filename)) {
                abort(404);
            }
            $path = $imgBase . '/' . $filename;
            if (!file_exists($path)) {
                abort(404);
            }
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                default       => 'application/octet-stream',
            };
            // Discard any pre-buffered output (e.g. stray whitespace from PHP files)
            // before streaming binary content, so the file bytes are sent clean.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=31536000');
            header('Content-Length: ' . filesize($path));
            readfile($path);

            exit;
        })->name('notices.assets.img');
    }

    /**
     * Load package route manifests.
     */
    protected function loadPackageRoutes(): void
    {
        $routeManifests = [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/api.php',
        ];

        foreach ($routeManifests as $routeManifest) {
            $this->loadRoutesFrom($routeManifest);
        }
    }

    /**
     * Register custom Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @deliveryLabel('email') or @deliveryLabel($id)
        Blade::directive('deliveryLabel', function ($expression) {
            return "<?php echo app(\\Dcplibrary\\Notices\\Services\\LookupLabelService::class)->getDeliveryLabel({$expression}); ?>";
        });

        // @notificationTypeLabel('hold') or @notificationTypeLabel($id)
        Blade::directive('notificationTypeLabel', function ($expression) {
            return "<?php echo app(\\Dcplibrary\\Notices\\Services\\LookupLabelService::class)->getNotificationTypeLabel({$expression}); ?>";
        });

        // @notificationStatusLabel('sent') or @notificationStatusLabel($id)
        Blade::directive('notificationStatusLabel', function ($expression) {
            return "<?php echo app(\\Dcplibrary\\Notices\\Services\\LookupLabelService::class)->getNotificationStatusLabel({$expression}); ?>";
        });
    }

    /**
     * Register scheduled tasks for automatic notification imports.
     *
     * Schedule based on Shoutbomb/Polaris export times documented in NOTIFICATIONS README.md:
     * - 5:00 AM: Notification Log & History exports (source of truth for Mail/Email)
     * - 8:05 AM: First FTP batch (holds, renewal, overdue)
     * - 9:05 AM: Second holds batch
     * - 1:05 PM: Third holds batch
     * - 5:05 PM: Fourth holds batch
     *
     * Import schedule runs 20-25 minutes after each export to allow time for:
     * - File uploads to complete
     * - LX Starter processing
     * - Delivery confirmations to be recorded
     */
    protected function registerScheduledTasks(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Morning import: 8:30 AM (covers 5:00 AM Notification Log + 8:05 AM FTP batch)
            $schedule->command('notices:import --date=today')
                ->dailyAt('08:30')
                ->timezone(config('app.timezone', 'UTC'))
                ->runInBackground()
                ->onSuccess(function () {
                    \Log::info('[Notices] Morning import completed successfully');
                })
                ->onFailure(function () {
                    \Log::error('[Notices] Morning import failed');
                })
            ;

            // Second holds import: 9:30 AM (covers 9:05 AM FTP batch)
            $schedule->command('notices:import --date=today')
                ->dailyAt('09:30')
                ->timezone(config('app.timezone', 'UTC'))
                ->runInBackground()
                ->onSuccess(function () {
                    \Log::info('[Notices] Second holds import completed successfully');
                })
                ->onFailure(function () {
                    \Log::error('[Notices] Second holds import failed');
                })
            ;

            // Afternoon import: 1:30 PM (covers 1:05 PM FTP batch)
            $schedule->command('notices:import --date=today')
                ->dailyAt('13:30')
                ->timezone(config('app.timezone', 'UTC'))
                ->runInBackground()
                ->onSuccess(function () {
                    \Log::info('[Notices] Afternoon import completed successfully');
                })
                ->onFailure(function () {
                    \Log::error('[Notices] Afternoon import failed');
                })
            ;

            // Evening import: 5:30 PM (covers 5:05 PM FTP batch)
            $schedule->command('notices:import --date=today')
                ->dailyAt('17:30')
                ->timezone(config('app.timezone', 'UTC'))
                ->runInBackground()
                ->onSuccess(function () {
                    \Log::info('[Notices] Evening import completed successfully');
                })
                ->onFailure(function () {
                    \Log::error('[Notices] Evening import failed');
                })
            ;
        });
    }

    /**
     * Register query performance logging to track slow database queries.
     *
     * When enabled, logs queries that exceed the configured threshold.
     * This complements MySQL slow query logging by capturing application context.
     */
    protected function registerQueryPerformanceLogging(): void
    {
        if (!config('notices.query_logging_enabled', false)) {
            return;
        }

        $threshold = config('notices.query_logging_threshold', 100);
        $channel   = config('notices.query_logging_channel', 'stack');

        DB::listen(function ($query) use ($threshold, $channel) {
            // Convert query time to milliseconds (Laravel provides microseconds)
            $timeMs = $query->time;

            if ($timeMs >= $threshold) {
                Log::channel($channel)->warning('Slow query detected in notices package', [
                    'time_ms'      => $timeMs,
                    'sql'          => $query->sql,
                    'bindings'     => $query->bindings,
                    'connection'   => $query->connectionName,
                    'threshold_ms' => $threshold,
                ]);
            }
        });
    }

    // ── dcplibrary/ui integration ─────────────────────────────────────────────

    /**
     * No-op Livewire Blade directives so dcplibrary/ui staff layout compiles without livewire/livewire.
     */
    protected function registerLivewireBladeFallbacks(): void
    {
        if (class_exists(\Livewire\Livewire::class)) {
            return;
        }

        Blade::directive('livewireStyles', static fn () => '<?php ?>');
        Blade::directive('livewireScripts', static fn () => '<?php ?>');
    }

    /**
     * Register views and anonymous components for x-dcpl:: (when not already registered by UiServiceProvider).
     */
    protected function registerDcplUiViews(): void
    {
        // Package-local dcpl:: views (*-header chrome, etc.) — prepended so they ship with notices until merged into dcplibrary/ui.
        $dcplExtensions = __DIR__ . '/../resources/views/dcpl-ui-extensions';
        if (is_dir($dcplExtensions)) {
            View::prependNamespace('dcpl', $dcplExtensions);
        }

        $dcplUiViews = $this->resolveDcplUiViewsPath();
        if ($dcplUiViews) {
            $this->loadViewsFrom($dcplUiViews, 'dcpl');
            Blade::anonymousComponentNamespace('dcpl::components', 'dcpl');
        }
    }

    protected function resolveDcplUiViewsPath(): ?string
    {
        // Prefer this package's Composer install (Testbench base_path often lacks vendor/dcplibrary/ui).
        $candidates = [
            realpath(__DIR__ . '/../vendor/dcplibrary/ui/resources/views'),
            is_dir(base_path('vendor/dcplibrary/ui/resources/views')) ? realpath(base_path('vendor/dcplibrary/ui/resources/views')) : null,
            realpath(__DIR__ . '/../../dcplibrary-ui/resources/views'),
        ];

        foreach (array_filter($candidates) as $path) {
            if ($path && is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function registerDcplUiCssRoute(): void
    {
        if (\Illuminate\Support\Facades\Route::has('dcpl.ui.css')) {
            return;
        }

        // Always register the named route so staff layouts can call route('dcpl.ui.css')
        // in Testbench (where base_path vendor differs from the package vendor tree).
        $css = $this->resolveDcplUiCssPath();

        \Illuminate\Support\Facades\Route::get('dcpl/ui/css', static function () use ($css) {
            if ($css && is_file($css)) {
                $cacheControl = app()->environment('production')
                    ? 'public, max-age=31536000'
                    : 'no-cache, private, must-revalidate';

                return response()->file($css, [
                    'Content-Type'  => 'text/css',
                    'Cache-Control' => $cacheControl,
                ]);
            }

            return response('/* dcpl ui css unavailable */', 200, [
                'Content-Type' => 'text/css',
            ]);
        })->name('dcpl.ui.css');
    }

    protected function resolveDcplUiCssPath(): ?string
    {
        // Prefer this package's Composer install (Testbench base_path often lacks vendor/dcplibrary/ui).
        $candidates = [
            realpath(__DIR__ . '/../vendor/dcplibrary/ui/resources/dist/dcpl.css'),
            is_file(base_path('vendor/dcplibrary/ui/resources/dist/dcpl.css'))
                ? realpath(base_path('vendor/dcplibrary/ui/resources/dist/dcpl.css'))
                : null,
            realpath(__DIR__ . '/../../ui/resources/dist/dcpl.css'),
            realpath(__DIR__ . '/../../dcplibrary-ui/resources/dist/dcpl.css'),
        ];

        foreach (array_filter($candidates) as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function shareDcplUiCssVersion(): void
    {
        $path = $this->resolveDcplUiCssPath();
        if ($path) {
            View::share('dcplUiCssVersion', substr(md5_file($path), 0, 8));
        }
    }
}
