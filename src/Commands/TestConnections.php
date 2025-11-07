<?php

namespace Dcplibrary\PolarisNotifications\Commands;

use Dcplibrary\PolarisNotifications\Services\PolarisImportService;
use Dcplibrary\PolarisNotifications\Services\ShoutbombFTPService;
use Illuminate\Console\Command;

class TestConnections extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polaris:test-connections
                            {--polaris : Test only Polaris MSSQL connection}
                            {--shoutbomb : Test only Shoutbomb FTP connection}';

    /**
     * The console command description.
     */
    protected $description = 'Test database and FTP connections for the Polaris Notifications package';

    /**
     * Execute the console command.
     */
    public function handle(PolarisImportService $polarisImporter, ShoutbombFTPService $ftpService): int
    {
        $this->info('🔍 Testing connections...');
        $this->newLine();

        $allPassed = true;

        // Test Polaris connection
        if (!$this->option('shoutbomb')) {
            $this->line('→ Testing Polaris MSSQL connection...');

            $polarisResult = $polarisImporter->testConnection();

            if ($polarisResult['success']) {
                $this->info('  ✅ Polaris connection successful');
                $this->line('  📊 Total notifications in database: ' . number_format($polarisResult['total_notifications']));
            } else {
                $this->error('  ❌ Polaris connection failed');
                $this->error('  Error: ' . $polarisResult['error']);
                $allPassed = false;
            }

            $this->newLine();
        }

        // Test Shoutbomb FTP connection
        if (!$this->option('polaris')) {
            if (config('polaris-notifications.shoutbomb.enabled')) {
                $this->line('→ Testing Shoutbomb FTP connection...');

                $ftpResult = $ftpService->testConnection();

                if ($ftpResult['success']) {
                    $this->info('  ✅ Shoutbomb FTP connection successful');
                } else {
                    $this->error('  ❌ Shoutbomb FTP connection failed');
                    $this->error('  ' . $ftpResult['message']);
                    $allPassed = false;
                }

                $this->newLine();
            } else {
                $this->warn('⚠️  Shoutbomb FTP is disabled in configuration');
                $this->newLine();
            }
        }

        // Summary
        $this->line('─────────────────────────────────────────');
        if ($allPassed) {
            $this->info('✅ All connection tests passed!');
        } else {
            $this->error('❌ Some connection tests failed. Please check the configuration.');
        }
        $this->line('─────────────────────────────────────────');
        $this->newLine();

        // Display configuration info
        if ($this->option('verbose')) {
            $this->info('📋 Configuration:');
            $this->newLine();

            $this->table(
                ['Setting', 'Value'],
                [
                    ['Polaris Host', config('polaris-notifications.polaris_connection.host')],
                    ['Polaris Database', config('polaris-notifications.polaris_connection.database')],
                    ['Reporting Org ID', config('polaris-notifications.reporting_org_id')],
                    ['Shoutbomb Enabled', config('polaris-notifications.shoutbomb.enabled') ? 'Yes' : 'No'],
                    ['Shoutbomb FTP Host', config('polaris-notifications.shoutbomb.ftp.host', 'Not configured')],
                    ['Default Import Days', config('polaris-notifications.import.default_days')],
                    ['Batch Size', config('polaris-notifications.import.batch_size')],
                ]
            );
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}
