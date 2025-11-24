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
                            {--file= : Import from local file instead of FTP}
                            {--type= : Notification type for local file (holds, overdue, renew)}';

    protected $description = 'Import Shoutbomb submission files (what was sent to Shoutbomb)';

    public function handle(ShoutbombSubmissionImporter $importer): int
    {
        $this->info('🚀 Starting Shoutbomb submission import...');
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
