<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\ShoutbombPhoneNoticeImporter;
use Illuminate\Console\Command;

class ImportShoutbombPhoneNotices extends Command
{
    protected $signature = 'notices:import-phone-notices
                            {--file= : Import from local file instead of FTP}';

    protected $description = 'Import PhoneNotices.csv for verification/confirmation of notices sent to Shoutbomb';

    public function handle(ShoutbombPhoneNoticeImporter $importer): int
    {
        $this->info('🔍 Starting PhoneNotices.csv import (Verification/Confirmation)...');
        $this->newLine();

        // Import from local file (for testing)
        if ($this->option('file')) {
            return $this->importFromFile($importer);
        }

        // Import from FTP
        return $this->importFromFTP($importer);
    }

    /**
     * Import from FTP.
     */
    protected function importFromFTP(ShoutbombPhoneNoticeImporter $importer): int
    {
        $this->line("📥 Importing PhoneNotices.csv from FTP...");
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
        });

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
    protected function importFromFile(ShoutbombPhoneNoticeImporter $importer): int
    {
        $filePath = $this->option('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->line("📥 Importing from file: {$filePath}");
        $this->newLine();

        try {
            $results = $importer->importFromFile($filePath);

            $this->info("✅ Imported {$results['imported']} phone notices");
            $this->line("   File: {$results['file']}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
