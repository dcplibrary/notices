<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\PolarisPhoneNoticeImporter;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * Import Polaris PhoneNotices.csv
 * 
 * This command imports PhoneNotices.csv, which is a Polaris-generated export
 * used for verification of notices sent to Shoutbomb.
 */
class ImportPolarisPhoneNotices extends Command
{
    protected $signature = 'notices:import-polaris-phone-notices
                            {--file= : Import from local file instead of FTP}
                            {--days= : Number of days back to import (defaults to notices.import.default_days)}
                            {--start-date= : Start date (Y-m-d)}
                            {--end-date= : End date (Y-m-d)}';

    protected $description = 'Import Polaris PhoneNotices.csv for verification of notices sent to Shoutbomb';

    public function handle(PolarisPhoneNoticeImporter $importer): int
    {
        $this->info('🔍 Starting Polaris PhoneNotices.csv import (Verification)...');
        $this->newLine();

        // Resolve date range
        [$startDate, $endDate] = $this->resolveDateRange();

        // Import from local file (for testing)
        if ($this->option('file')) {
            return $this->importFromFile($importer, $startDate, $endDate);
        }

        // Import from FTP
        return $this->importFromFTP($importer, $startDate, $endDate);
    }

    /**
     * Import from FTP.
     */
    protected function importFromFTP(PolarisPhoneNoticeImporter $importer, ?Carbon $startDate, ?Carbon $endDate): int
    {
        $this->line("📥 Importing PhoneNotices.csv from FTP...");
        if ($startDate && $endDate) {
            $this->line("   Date filter: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        }
        $this->newLine();

        // Create progress bar (will be initialized when we know the total)
        $progressBar = null;

        $results = $importer->importFromFTP(function ($current, $total) use (&$progressBar) {
            if (!$progressBar) {
                // Initialize progress bar on first call
                $this->newLine();
                $progressBar = $this->output->createProgressBar($total);
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - Importing records');
                $progressBar->start();
            }
            $progressBar->setProgress($current);
        }, $startDate, $endDate);

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine(2);
        }

        // Display results
        $this->line('─────────────────────────────────────────');
        $this->info('✅ PhoneNotices.csv Import completed!');
        $this->line('─────────────────────────────────────────');

        if ($results['file']) {
            $this->table(
                ['Field', 'Value'],
                [
                    ['File', $results['file']],
                    ['Imported', $results['imported']],
                    ['Skipped', $results['skipped']],
                    ['Errors', $results['errors']],
                ]
            );
        } else {
            $this->warn('⚠️  PhoneNotices.csv not found on FTP server');
        }

        $this->newLine();

        return $results['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Import from local file.
     */
    protected function importFromFile(PolarisPhoneNoticeImporter $importer, ?Carbon $startDate, ?Carbon $endDate): int
    {
        $filePath = $this->option('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->line("📥 Importing from file: {$filePath}");
        if ($startDate && $endDate) {
            $this->line("   Date filter: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        }
        $this->newLine();

        try {
            $results = $importer->importFromFile($filePath, $startDate, $endDate);

            $this->info("✅ Imported {$results['imported']} phone notices");
            $this->line("   File: {$results['file']}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Resolve the desired date range based on options.
     */
    protected function resolveDateRange(): array
    {
        $startDate = null;
        $endDate = null;

        if ($this->option('start-date') && $this->option('end-date')) {
            $startDate = Carbon::parse($this->option('start-date'))->startOfDay();
            $endDate = Carbon::parse($this->option('end-date'))->endOfDay();
        } elseif ($this->option('days')) {
            $days = (int) $this->option('days');
            $endDate = now()->endOfDay();
            $startDate = now()->subDays($days)->startOfDay();
        }

        return [$startDate, $endDate];
    }
}
