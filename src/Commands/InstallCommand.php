<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Database\Seeders\DeliveryMethodSeeder;
use Dcplibrary\Notices\Database\Seeders\NoticesSettingsSeeder;
use Dcplibrary\Notices\Database\Seeders\NotificationStatusSeeder;
use Dcplibrary\Notices\Database\Seeders\NotificationTypeSeeder;
use Dcplibrary\Notices\Database\Seeders\PopulateReferenceDataLabelsSeeder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'notices:install
                            {--force : Skip confirmation prompts}
                            {--fresh : Drop existing tables and recreate them}
                            {--skip-migrations : Skip running migrations}
                            {--skip-seed : Skip seeding reference data}';

    protected $description = 'Install the Notices package: run migrations and seed required reference data';

    public function handle(): int
    {
        $this->info('');
        $this->info('  ╔═══════════════════════════════════════╗');
        $this->info('  ║     Notices Package Installation      ║');
        $this->info('  ╚═══════════════════════════════════════╝');
        $this->info('');

        // Check if tables already exist
        $tablesExist = Schema::hasTable('notification_logs') ||
                       Schema::hasTable('notification_types') ||
                       Schema::hasTable('notification_statuses') ||
                       Schema::hasTable('delivery_methods');

        if ($tablesExist && !$this->option('force') && !$this->option('fresh')) {
            $this->warn('Some Notices tables already exist.');
            if (!$this->confirm('Do you want to continue? This will run any pending migrations and update reference data.')) {
                $this->info('Installation cancelled.');

                return Command::SUCCESS;
            }
        }

        if ($this->option('fresh')) {
            if (!$this->option('force') && !$this->confirm('⚠️  This will DROP all Notices tables and recreate them. All data will be lost. Continue?', false)) {
                $this->info('Installation cancelled.');

                return Command::SUCCESS;
            }
            $this->dropTables();
        }

        $results = [];

        // Step 1: Run migrations
        if (!$this->option('skip-migrations')) {
            $results['migrations'] = $this->runMigrations();
        } else {
            $this->line('→ Skipping migrations (--skip-migrations)');
            $results['migrations'] = ['status' => 'skipped'];
        }

        // Step 2: Seed reference data
        if (!$this->option('skip-seed')) {
            $results['seed'] = $this->seedReferenceData();
        } else {
            $this->line('→ Skipping seed (--skip-seed)');
            $results['seed'] = ['status' => 'skipped'];
        }

        // Display summary
        $this->displaySummary($results);

        return Command::SUCCESS;
    }

    /**
     * Drop all Notices tables for fresh install.
     */
    protected function dropTables(): void
    {
        $this->line('→ Dropping existing tables...');

        $tables = [
            'daily_notification_summary',
            'notification_logs',
            'notification_settings',
            'notification_statuses',
            'notification_types',
            'delivery_methods',
            'shoutbomb_deliveries',
            'shoutbomb_keyword_usage',
            'shoutbomb_registrations',
            'shoutbomb_submissions',
            'polaris_phone_notices',
            'patron_preferences',
            'sync_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->line("  Dropped: {$table}");
            }
        }

        $this->info('  ✓ Tables dropped');
        $this->newLine();
    }

    /**
     * Run package migrations.
     */
    protected function runMigrations(): array
    {
        $this->line('→ Running migrations...');

        try {
            $exitCode = Artisan::call('migrate', [
                '--path' => 'vendor/dcplibrary/notices/src/Database/Migrations',
                '--force' => true,
            ]);
            $output = Artisan::output();

            if ($exitCode === 0) {
                $this->info('  ✓ Migrations completed');

                return ['status' => 'success', 'message' => trim($output)];
            } else {
                $this->error('  ✗ Migration failed');

                return ['status' => 'error', 'message' => trim($output)];
            }
        } catch (Exception $e) {
            $this->error("  ✗ Migration error: {$e->getMessage()}");

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Seed reference data (notification types, statuses, delivery methods, settings).
     */
    protected function seedReferenceData(): array
    {
        $this->newLine();
        $this->line('→ Seeding reference data...');

        $seeders = [
            'Delivery Methods' => DeliveryMethodSeeder::class,
            'Notification Types' => NotificationTypeSeeder::class,
            'Notification Statuses' => NotificationStatusSeeder::class,
            'Reference Data Labels' => PopulateReferenceDataLabelsSeeder::class,
            'Default Settings' => NoticesSettingsSeeder::class,
        ];

        $results = [];
        $hasErrors = false;

        foreach ($seeders as $name => $seederClass) {
            try {
                Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ]);
                $this->line("  ✓ {$name}");
                $results[$name] = 'success';
            } catch (Exception $e) {
                $this->error("  ✗ {$name}: {$e->getMessage()}");
                $results[$name] = 'error';
                $hasErrors = true;
            }
        }

        $this->newLine();
        if ($hasErrors) {
            $this->warn('  ⚠ Some seeders had errors');

            return ['status' => 'partial', 'details' => $results];
        }

        $this->info('  ✓ Reference data seeded');

        return ['status' => 'success', 'details' => $results];
    }

    /**
     * Display installation summary.
     */
    protected function displaySummary(array $results): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('  Installation Summary');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        $rows = [];

        // Migrations status
        $migrationStatus = $results['migrations']['status'] ?? 'unknown';
        $migrationIcon = match ($migrationStatus) {
            'success' => '✓',
            'skipped' => '○',
            'error' => '✗',
            default => '?',
        };
        $rows[] = ['Migrations', "{$migrationIcon} " . ucfirst($migrationStatus)];

        // Seed status
        $seedStatus = $results['seed']['status'] ?? 'unknown';
        $seedIcon = match ($seedStatus) {
            'success' => '✓',
            'skipped' => '○',
            'partial' => '⚠',
            'error' => '✗',
            default => '?',
        };
        $rows[] = ['Reference Data', "{$seedIcon} " . ucfirst($seedStatus)];

        $this->table(['Component', 'Status'], $rows);

        $this->newLine();
        $this->info('  Next steps:');
        $this->line('  1. Configure your database connections in config/notices.php');
        $this->line('  2. Import data: php artisan notices:import-polaris');
        $this->line('  3. Or sync all:  php artisan notices:sync-all');
        $this->line('  4. View dashboard at /notices');
        $this->newLine();

        $allSuccess = ($results['migrations']['status'] ?? '') !== 'error' &&
                      ($results['seed']['status'] ?? '') !== 'error';

        if ($allSuccess) {
            $this->info('  ✓ Installation complete!');
        } else {
            $this->warn('  ⚠ Installation completed with some errors. Please review the output above.');
        }

        $this->newLine();
    }
}
