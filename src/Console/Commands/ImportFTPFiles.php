<?php

namespace Dcplibrary\Notices\Console\Commands;

use Carbon\Carbon;
use Dcplibrary\Notices\Services\PolarisPhoneNoticeImporter;
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Dcplibrary\Notices\Services\PatronDeliveryPreferenceImporter;
use Illuminate\Console\Command;

class ImportFTPFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:import-ftp-files
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--all : Import all available files}
                            {--import-patrons : Also import patron delivery preference files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import PhoneNotices and Shoutbomb submission files from FTP';

    protected PolarisPhoneNoticeImporter $phoneNoticeImporter;
    protected ShoutbombSubmissionImporter $submissionImporter;
    protected ?PatronDeliveryPreferenceImporter $patronImporter;

    public function __construct(
        PolarisPhoneNoticeImporter $phoneNoticeImporter,
        ShoutbombSubmissionImporter $submissionImporter,
        PatronDeliveryPreferenceImporter $patronImporter = null
    ) {
        parent::__construct();
        $this->phoneNoticeImporter = $phoneNoticeImporter;
        $this->submissionImporter = $submissionImporter;
        $this->patronImporter = $patronImporter;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== FTP Files Import ===');
        $this->newLine();

        // Determine date range
        [$fromDate, $toDate] = $this->determineDateRange();

        $this->info("📅 Date Range: {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");
        
        // Show patron import status
        $importPatrons = $this->option('import-patrons');
        $this->info('👥 Patron import: ' . ($importPatrons ? '<fg=green>ENABLED</>' : '<fg=gray>DISABLED</>'));
        $this->newLine();

        $totalRecords = 0;
        $totalFiles = 0;

        // Import PhoneNotices
        try {
            $this->info('📱 Importing PhoneNotices...');
            $phoneResult = $this->phoneNoticeImporter->importAllFromFTP(
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

            $this->info("  ✅ PhoneNotices: {$phoneResult['total_records']} records from {$phoneResult['files_processed']} files");
            $totalRecords += $phoneResult['total_records'];
            $totalFiles += $phoneResult['files_processed'];
        } catch (\Exception $e) {
            $this->error("  ❌ PhoneNotices import failed: {$e->getMessage()}");
        }

        $this->newLine();

        // Import Shoutbomb Submissions
        try {
            $this->info('📨 Importing Shoutbomb Submissions...');
            
            $submissionResult = $this->submissionImporter->importAllFromFTP(
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

            $holdRecords = $submissionResult['holds']['records'] ?? 0;
            $overdueRecords = $submissionResult['overdues']['records'] ?? 0;
            $renewalRecords = $submissionResult['renewals']['records'] ?? 0;

            $this->info("  ✅ Holds: {$holdRecords} records");
            $this->info("  ✅ Overdues: {$overdueRecords} records");
            $this->info("  ✅ Renewals: {$renewalRecords} records");

            $submissionTotal = $holdRecords + $overdueRecords + $renewalRecords;
            $totalRecords += $submissionTotal;
            $totalFiles += ($submissionResult['holds']['files'] ?? 0)
                + ($submissionResult['overdues']['files'] ?? 0)
                + ($submissionResult['renewals']['files'] ?? 0);
        } catch (\Exception $e) {
            $this->error("  ❌ Shoutbomb submissions import failed: {$e->getMessage()}");
        }

        // Import Patron Delivery Preferences (if enabled and importer available)
        if ($importPatrons) {
            $this->newLine();
            
            if ($this->patronImporter) {
                try {
                    $this->info('👥 Importing Patron Delivery Preferences...');
                    
                    $patronResult = $this->patronImporter->importAllFromFTP(
                        $fromDate,
                        $toDate,
                        function ($current, $total, $filename, $isNewFile, $skipped = false) {
                            if ($skipped && $filename) {
                                $this->line("  ⏭️  <fg=yellow>Skipping (already processed):</> <fg=cyan>{$filename}</>");
                            } elseif ($isNewFile && $filename) {
                                $this->info("  📄 Importing: <fg=cyan>{$filename}</>");
                            } elseif ($current > 0 && $total > 0 && $current % 500 === 0) {
                                $this->line("    Processing: {$current}/{$total}");
                            }
                        }
                    );

                    $voiceNew = $patronResult['voice']['new'] ?? 0;
                    $voiceChanged = $patronResult['voice']['changed'] ?? 0;
                    $textNew = $patronResult['text']['new'] ?? 0;
                    $textChanged = $patronResult['text']['changed'] ?? 0;

                    $this->info("  ✅ Voice: {$patronResult['voice']['total']} total ({$voiceNew} new, {$voiceChanged} changed)");
                    $this->info("  ✅ Text: {$patronResult['text']['total']} total ({$textNew} new, {$textChanged} changed)");

                    $totalRecords += ($patronResult['voice']['total'] ?? 0) + ($patronResult['text']['total'] ?? 0);
                    $totalFiles += ($patronResult['voice']['files'] ?? 0) + ($patronResult['text']['files'] ?? 0);
                } catch (\Exception $e) {
                    $this->error("  ❌ Patron import failed: {$e->getMessage()}");
                }
            } else {
                $this->warn('  ⚠️  PatronDeliveryPreferenceImporter not available. Skipping patron import.');
            }
        }

        $this->newLine();
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
