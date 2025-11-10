<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\PolarisImportService;
use Dcplibrary\Notices\Services\ShoutbombFTPService;
use Dcplibrary\Notices\Services\EmailReportService;
use Illuminate\Console\Command;

class TestConnections extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notices:test-connections
                            {--polaris : Test only Polaris MSSQL connection}
                            {--shoutbomb : Test only Shoutbomb FTP connection}
                            {--email : Test only Email IMAP connection}';

    /**
     * The console command description.
     */
    protected $description = 'Test database, FTP, and email connections for the Polaris Notifications package';

    /**
     * Execute the console command.
     */
    public function handle(
        PolarisImportService $polarisImporter,
        ShoutbombFTPService $ftpService,
        EmailReportService $emailService
    ): int
    {
        $this->info('🔍 Testing connections...');
        $this->newLine();

        $allPassed = true;

        // Test Polaris connection
        if (!$this->option('shoutbomb') && !$this->option('email')) {
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
        if (!$this->option('polaris') && !$this->option('email')) {
            if (config('notices.shoutbomb.enabled')) {
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

        // Test Email IMAP connection
        if (!$this->option('polaris') && !$this->option('shoutbomb')) {
            if (config('notices.email_reports.enabled')) {
                $this->line('→ Testing Email IMAP connection...');

                $emailResult = $emailService->testConnection();

                if ($emailResult['success']) {
                    $this->info('  ✅ Email connection successful');
                    if (isset($emailResult['details']['total_messages'])) {
                        $this->line('  📧 Total messages in mailbox: ' . number_format($emailResult['details']['total_messages']));
                    }
                } else {
                    $this->error('  ❌ Email connection failed');
                    $this->error('  ' . $emailResult['message']);
                    $allPassed = false;
                }

                $this->newLine();
            } else {
                $this->warn('⚠️  Email reports are disabled in configuration');
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
                    ['Polaris Host', config('notices.polaris_connection.host')],
                    ['Polaris Database', config('notices.polaris_connection.database')],
                    ['Reporting Org ID', config('notices.reporting_org_id')],
                    ['Shoutbomb Enabled', config('notices.shoutbomb.enabled') ? 'Yes' : 'No'],
                    ['Shoutbomb FTP Host', config('notices.shoutbomb.ftp.host', 'Not configured')],
                    ['Email Reports Enabled', config('notices.email_reports.enabled') ? 'Yes' : 'No'],
                    ['Email Host', config('notices.email_reports.connection.host', 'Not configured')],
                    ['Default Import Days', config('notices.import.default_days')],
                    ['Batch Size', config('notices.import.batch_size')],
                ]
            );
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }
}
