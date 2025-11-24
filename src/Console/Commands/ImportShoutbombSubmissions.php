<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Illuminate\Console\Command;

class ImportShoutbombSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:import-shoutbomb-submissions
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--all : Import all available submissions}
                            {--type= : Import specific type only (holds, overdues, renewals)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Shoutbomb submission files (holds, overdues, renewals)';

    protected ShoutbombSubmissionImporter $importer;

    public function __construct(ShoutbombSubmissionImporter $importer)
    {
        parent::__construct();
        $this->importer = $importer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Shoutbomb Submissions Import ===');
        $this->newLine();

        // Determine date range
        [$fromDate, $toDate] = $this->determineDateRange();

        $this->info("📅 Date Range: {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");
        $this->newLine();

        $type = $this->option('type');
        $types = $type ? [$type] : ['holds', 'overdues', 'renewals'];

        $totalRecords = 0;
        $totalFiles = 0;

        foreach ($types as $submissionType) {
            try {
                $this->info("📨 Importing {$submissionType}...");
                
                $result = $this->importer->importSubmissionType(
                    $submissionType,
                    $fromDate,
                    $toDate,
                    function ($current, $total, $filename, $isNewFile) {
                        if ($isNewFile && $filename) {
                            $this->info("  📄 Importing: <fg=cyan>{$filename}</>");
                        } elseif ($current > 0 && $total > 0 && $current % 500 === 0) {
                            $this->line("    Processing: {$current}/{$total}");
                        }
                    }
                );

                $this->info("  ✅ {$submissionType}: {$result['records']} records from {$result['files']} files");
                $totalRecords += $result['records'];
                $totalFiles += $result['files'];
            } catch (\Exception $e) {
                $this->error("  ❌ {$submissionType} import failed: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info("✨ Import Complete!");
        $this->info("   📊 Total: {$totalRecords} records from {$totalFiles} files");

        return self::SUCCESS;
    }

    /**
     * Determine the date range for import
     */
    protected function determineDateRange(): array
    {
        if ($this->option('all')) {
            // Import all available files (last 365 days)
            return [
                Carbon::now()->subYear(),
                Carbon::now(),
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

        // Default to today only
        return [
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
        ];
    }
}
