<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\PolarisImportService;
use Illuminate\Console\Command;

class ImportPolarisCommand extends Command
{
    protected $signature = 'notices:import-polaris 
                            {--days= : Number of days to import (default: from config)}
                            {--from= : Start date (Y-m-d format)}
                            {--to= : End date (Y-m-d format)}';

    protected $description = 'Import notifications from Polaris database';

    public function handle(PolarisImportService $importService)
    {
        $this->info('Starting Polaris import...');

        try {
            $days = $this->option('days');
            $startDate = $this->option('from') ? \Carbon\Carbon::parse($this->option('from')) : null;
            $endDate = $this->option('to') ? \Carbon\Carbon::parse($this->option('to')) : null;

            $result = $importService->importNotifications($days, $startDate, $endDate);

            $this->info("Imported {$result['imported']} notifications from Polaris");
            $this->line("Skipped: {$result['skipped']} duplicates");
            $this->line("Errors: {$result['errors']}");
            $this->line("Date range: {$result['start_date']} to {$result['end_date']}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Polaris import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
