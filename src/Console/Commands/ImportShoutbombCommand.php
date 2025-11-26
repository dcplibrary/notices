<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Exception;
use Illuminate\Console\Command;

class ImportShoutbombCommand extends Command
{
    protected $signature = 'notices:import-shoutbomb 
                            {--start-date= : Start date (Y-m-d format, default: yesterday)}';

    protected $description = 'Import Shoutbomb submission files from FTP';

    public function handle(ShoutbombSubmissionImporter $importer)
    {
        $this->info('Starting Shoutbomb submission import...');

        try {
            $startDate = $this->option('start-date')
                ? Carbon::parse($this->option('start-date'))
                : null;

            $result = $importer->importFromFTP($startDate);

            $totalImported = $result['holds'] + $result['overdues'] + $result['renewals'];
            $this->info("Imported {$totalImported} records from Shoutbomb");
            $this->line("Holds: {$result['holds']}");
            $this->line("Overdues: {$result['overdues']}");
            $this->line("Renewals: {$result['renewals']}");
            $this->line("Voice patrons: {$result['voice_patrons']}");
            $this->line("Text patrons: {$result['text_patrons']}");
            $this->line("Errors: {$result['errors']}");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('✗ Shoutbomb import failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
