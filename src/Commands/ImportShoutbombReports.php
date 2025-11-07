<?php

namespace Dcplibrary\PolarisNotifications\Commands;

use Dcplibrary\PolarisNotifications\Services\ShoutbombFTPService;
use Illuminate\Console\Command;

class ImportShoutbombReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polaris:import-shoutbomb
                            {--type=all : Type of reports to import (monthly, weekly, daily-invalid, daily-undelivered, all)}';

    /**
     * The console command description.
     */
    protected $description = 'Import Shoutbomb reports from FTP server';

    /**
     * Execute the console command.
     */
    public function handle(ShoutbombFTPService $ftpService): int
    {
        if (!config('polaris-notifications.shoutbomb.enabled')) {
            $this->warn('⚠️  Shoutbomb import is disabled in configuration.');
            return Command::SUCCESS;
        }

        $this->info('🚀 Starting Shoutbomb report import...');
        $this->newLine();

        $type = $this->option('type');
        $results = [];

        try {
            // Import based on type
            switch ($type) {
                case 'monthly':
                    $this->info('📥 Importing monthly reports...');
                    $results['monthly'] = $ftpService->importMonthlyReports();
                    break;

                case 'weekly':
                    $this->info('📥 Importing weekly reports...');
                    $results['weekly'] = $ftpService->importWeeklyReports();
                    break;

                case 'daily-invalid':
                    $this->info('📥 Importing daily invalid phone reports...');
                    $results['daily_invalid'] = $ftpService->importDailyInvalidReports();
                    break;

                case 'daily-undelivered':
                    $this->info('📥 Importing daily undelivered voice reports...');
                    $results['daily_undelivered'] = $ftpService->importDailyUndeliveredReports();
                    break;

                case 'all':
                default:
                    $this->info('📥 Importing all report types...');
                    $this->newLine();

                    $this->line('→ Monthly reports...');
                    $results['monthly'] = $ftpService->importMonthlyReports();

                    $this->line('→ Weekly reports...');
                    $results['weekly'] = $ftpService->importWeeklyReports();

                    $this->line('→ Daily invalid phone reports...');
                    $results['daily_invalid'] = $ftpService->importDailyInvalidReports();

                    $this->line('→ Daily undelivered voice reports...');
                    $results['daily_undelivered'] = $ftpService->importDailyUndeliveredReports();
                    break;
            }

            // Display results
            $this->newLine();
            $this->line('─────────────────────────────────────────');
            $this->info('✅ Shoutbomb import completed!');
            $this->line('─────────────────────────────────────────');

            foreach ($results as $reportType => $result) {
                if ($result['success']) {
                    $stats = $result['stats'] ?? [];
                    $filesProcessed = $stats['files_processed'] ?? 0;

                    $this->info(ucfirst(str_replace('_', ' ', $reportType)) . ': ' . $filesProcessed . ' file(s) processed');

                    if ($this->option('verbose') && isset($stats['total_imported'])) {
                        foreach ($stats['total_imported'] as $imported) {
                            $this->line('  • Registrations: ' . ($imported['registrations'] ?? 0));
                            $this->line('  • Keyword usage: ' . ($imported['keyword_usage'] ?? 0));
                            $this->line('  • Deliveries: ' . ($imported['deliveries'] ?? 0));
                        }
                    }
                } else {
                    $this->error(ucfirst(str_replace('_', ' ', $reportType)) . ': Failed - ' . ($result['error'] ?? 'Unknown error'));
                }
            }

            $this->line('─────────────────────────────────────────');
            $this->newLine();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Import failed: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
