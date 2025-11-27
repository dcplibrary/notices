<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Exception;
use Illuminate\Console\Command;

class ImportShoutbombCommand extends Command
{
    protected $signature = 'notices:import-shoutbomb 
                            {--date= : Import submissions for a specific date (Y-m-d)}
                            {--start= : Alias for --date (Y-m-d)}
                            {--days= : Number of days back to import (uses latest date)}
                            {--all : Import all available submission files from FTP}
                            {--start-date= : [deprecated] Alias for --date (Y-m-d)}';

    protected $description = 'Import Shoutbomb submission files from FTP';

    public function handle(ShoutbombSubmissionImporter $importer)
    {
        $this->info('Starting Shoutbomb submission import...');

        try {
            if ($this->option('all')) {
                $this->warn('⚠️  Importing all available Shoutbomb submission files from FTP. This may take a while.');

                $resultAll = $importer->importAllFromFTP();
                $totals = $resultAll['totals'] ?? [];

                $result = [
                    'holds' => $totals['holds'] ?? 0,
                    'overdues' => $totals['overdues'] ?? 0,
                    'renewals' => $totals['renewals'] ?? 0,
                    'voice_patrons' => $totals['voice_patrons'] ?? 0,
                    'text_patrons' => $totals['text_patrons'] ?? 0,
                    'errors' => $totals['errors'] ?? 0,
                ];

            } else {
                // Resolve a single date from canonical/alias flags
                $date = $this->option('date')
                    ?: $this->option('start')
                    ?: $this->option('start-date');

                if ($date) {
                    $startDate = Carbon::parse($date);
                } elseif ($this->option('days')) {
                    $days = (int) $this->option('days');
                    $startDate = Carbon::now()->subDays(max($days, 1) - 1)->startOfDay();
                } else {
                    $startDate = null; // importer default (yesterday) applies
                }

                $result = $importer->importFromFTP($startDate);
            }

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
