<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\PolarisNotificationImporter;
use Exception;
use Illuminate\Console\Command;

class ImportPolaris extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:import-polaris
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--all : Import all available notifications}
                            {--days= : Import notifications from the last N days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import notification logs from Polaris database';

    protected PolarisNotificationImporter $importer;

    public function __construct(PolarisNotificationImporter $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Polaris Notification Import ===');
        $this->newLine();

        // Determine date range
        [$fromDate, $toDate] = $this->determineDateRange();

        if ($fromDate && $toDate) {
            $this->info("📅 Date Range: {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");
        } else {
            $this->info('📅 Importing all available notifications');
        }
        $this->newLine();

        try {
            $result = $this->importer->import(
                $fromDate,
                $toDate,
                function ($current, $total) {
                    if ($current % 1000 === 0 || $current === $total) {
                        $this->line("  Processing: {$current}/{$total}");
                    }
                }
            );

            $this->newLine();
            $this->info("✅ Import Complete!");
            $this->info("   📊 Imported: {$result['records']} notifications");
            $this->info("   🆕 New: {$result['new']}");
            $this->info("   🔄 Updated: {$result['updated']}");
            $this->info("   ⏭️  Skipped: {$result['skipped']}");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Determine the date range for import.
     */
    protected function determineDateRange(): array
    {
        if ($this->option('all')) {
            // Import all available (no date filter)
            return [null, null];
        }

        if ($this->option('days')) {
            $days = (int) $this->option('days');

            return [
                Carbon::now()->subDays($days)->startOfDay(),
                Carbon::now()->endOfDay(),
            ];
        }

        $from = $this->option('from');
        $to = $this->option('to');

        if ($from && $to) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ];
        }

        // Default to last 30 days
        return [
            Carbon::now()->subDays(30)->startOfDay(),
            Carbon::now()->endOfDay(),
        ];
    }
}
