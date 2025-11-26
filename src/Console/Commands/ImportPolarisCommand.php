<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\PolarisImportService;
use Exception;
use Illuminate\Console\Command;

class ImportPolarisCommand extends Command
{
    protected $signature = 'notices:import-polaris 
                            {--days= : Number of days to import (default: from config)}
                            {--start= : Start date (Y-m-d format)}
                            {--end= : End date (Y-m-d format)}
                            {--all : Import full historical data (interactive)}';

    protected $description = 'Import notifications from Polaris database';

    public function handle(PolarisImportService $importService)
    {
        $this->info('Starting Polaris import...');

        try {
            // When --all is provided, delegate to the historical importer with
            // interactive confirmation (mirrors the older ImportNotifications command).
            if ($this->option('all')) {
                $this->warn('⚠️  Full historical import requested. This may take a while and import a large volume of data.');

                $start = $this->ask('Enter start date for historical import (Y-m-d)', '2020-01-01');
                $end = $this->ask('Enter end date (Y-m-d, or leave empty for today)', now()->format('Y-m-d'));

                if (!$this->confirm("Import all notifications from {$start} to {$end}?", true)) {
                    $this->info('Historical import cancelled.');

                    return Command::SUCCESS;
                }

                $result = $importService->importHistorical(
                    Carbon::parse($start),
                    $end ? Carbon::parse($end) : null
                );

            } else {
                $days = $this->option('days');
                $startDate = $this->option('start') ? Carbon::parse($this->option('start')) : null;
                $endDate = $this->option('end') ? Carbon::parse($this->option('end')) : null;

                $result = $importService->importNotifications($days, $startDate, $endDate);
            }

            $this->info("Imported {$result['imported']} notifications from Polaris");
            $this->line("Skipped: {$result['skipped']} duplicates");
            $this->line("Errors: {$result['errors']}");
            $this->line("Date range: {$result['start_date']} to {$result['end_date']}");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('✗ Polaris import failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
