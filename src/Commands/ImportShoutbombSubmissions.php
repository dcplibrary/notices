<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportShoutbombSubmissions extends Command
{
    protected $signature = 'notices:import-shoutbomb-submissions
                            {--days=1 : Number of days to import}
                            {--date= : Specific date to import (Y-m-d format)}
                            {--from= : Start date for multi-day import (Y-m-d)}
                            {--to= : End date for multi-day import (Y-m-d)}
                            {--file= : Import from local file instead of FTP}
                            {--type= : Notification type for local file (holds, overdue, renew)}
                            {--all : Import all available submission files from FTP}';

    protected $description = 'Import Shoutbomb submission files (what was sent to Shoutbomb)';

    public function handle(ShoutbombSubmissionImporter $importer): int
    {
        $this->info('🚀 Starting Shoutbomb submission import...');
        $this->newLine();

        // Import from local file (for testing)
        if ($this->option('file')) {
            return $this->importFromFile($importer);
        }

        // Import a range or all available dates from FTP
        if ($this->option('all') || $this->option('from') || $this->option('to')) {
            return $this->importAllFromFTP($importer);
        }

        // Import a single date from FTP (default: yesterday or --days ago)
        return $this->importFromFTP($importer);
    }

    /**
     * Import from FTP for a single date.
     */
    protected function importFromFTP(ShoutbombSubmissionImporter $importer): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDays((int) ($this->option('days') ?? 1));

        $this->line("📥 Importing submissions from: {$date->format('Y-m-d')}");

        if ($this->option('verbose')) {
            $this->line("   (Use --date=YYYY-MM-DD to import from a specific date)");
            $this->line("   FTP Host: " . config('notices.shoutbomb.ftp.host'));
        }

        $this->newLine();

        // Show progress as we go
        $this->line('→ Downloading and processing patron lists...');

        $results = $importer->importFromFTP($date);

        // Display results
        $this->newLine();
        $this->line('─────────────────────────────────────────');
        $this->info('✅ Import completed!');
        $this->line('─────────────────────────────────────────');

        $this->table(
            ['Type', 'Count'],
            [
                ['Holds', $results['holds']],
                ['Overdues', $results['overdues']],
                ['Renewals', $results['renewals']],
                ['Voice Patrons', $results['voice_patrons']],
                ['Text Patrons', $results['text_patrons']],
                ['Errors', $results['errors']],
            ]
        );

        $this->newLine();

        return $results['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Import all available submission files from FTP (all dates).
     */
    protected function importAllFromFTP(ShoutbombSubmissionImporter $importer): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;

        if ($from || $to) {
            $this->line('📥 Importing Shoutbomb submissions for date range from FTP...');
        } else {
            $this->line('📥 Importing ALL available Shoutbomb submission files from FTP...');
        }
        $this->newLine();

        $summary = $importer->importAllFromFTP($from, $to);

        $dates = $summary['dates'] ?? [];
        $totals = $summary['totals'] ?? [];

        if (empty($dates)) {
            $this->warn('No submission files were found on the FTP server.');
            return Command::FAILURE;
        }

        $this->info('Dates processed:');
        foreach ($dates as $d) {
            $this->line("  - {$d}");
        }
        $this->newLine();

        $this->line('─────────────────────────────────────────');
        $this->info('✅ Import-all completed!');
        $this->line('─────────────────────────────────────────');

        $this->table(
            ['Metric', 'Total'],
            [
                ['Holds', $totals['holds'] ?? 0],
                ['Overdues', $totals['overdues'] ?? 0],
                ['Renewals', $totals['renewals'] ?? 0],
                ['Voice Patrons', $totals['voice_patrons'] ?? 0],
                ['Text Patrons', $totals['text_patrons'] ?? 0],
                ['Errors', $totals['errors'] ?? 0],
            ]
        );

        $this->newLine();

        return ($totals['errors'] ?? 0) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Import from local file.
     */
    protected function importFromFile(ShoutbombSubmissionImporter $importer): int
    {
        $filePath = $this->option('file');
        $type = $this->option('type');

        if (!$type) {
            $this->error('--type is required when importing from file');
            $this->line('Valid types: holds, overdue, renew');
            return Command::FAILURE;
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->line("📥 Importing from file: {$filePath}");
        $this->line("   Type: {$type}");
        $this->newLine();

        try {
            $results = $importer->importFromFile($filePath, $type);

            $this->info("✅ Imported {$results['imported']} records");
            $this->line("   File: {$results['file']}");
            $this->line("   Type: {$results['type']}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
