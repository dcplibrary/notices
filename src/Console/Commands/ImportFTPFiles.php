<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\PolarisPhoneNoticeImporter;
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Import all FTP files (PhoneNotices + Shoutbomb submissions)
 *
 * This streamlined command imports both:
 * - PhoneNotices files (PhoneNotices.csv or PhoneNotices_YYYY-MM-DD_HH-MM-SS.txt)
 * - Shoutbomb submission files (holds, overdue, renew, voice_patrons, text_patrons)
 */
class ImportFTPFiles extends Command
{
    protected $signature = 'notices:import-ftp-files
                            {--start-date= : Start date (Y-m-d), defaults to today}
                            {--end-date= : End date (Y-m-d), defaults to today}
                            {--days= : Number of days back to import (alternative to date range)}
                            {--all : Import all available files regardless of date}';

    protected $description = 'Import all FTP files (PhoneNotices and Shoutbomb submissions)';

    public function handle(
        PolarisPhoneNoticeImporter $phoneNoticeImporter,
        ShoutbombSubmissionImporter $submissionImporter
    ): int {
        $this->info('🚀 Starting FTP Files Import...');
        $this->newLine();

        // Resolve date range
        [$startDate, $endDate] = $this->resolveDateRange();

        if ($startDate && $endDate) {
            $this->line("📅 Date range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        } elseif ($this->option('all')) {
            $this->line("📅 Importing ALL available files");
        }
        $this->newLine();

        $results = [
            'phone_notices' => ['imported' => 0, 'errors' => 0],
            'submissions' => [
                'holds' => 0,
                'overdues' => 0,
                'renewals' => 0,
                'voice_patrons' => 0,
                'text_patrons' => 0,
                'errors' => 0,
            ],
        ];

        // Step 1: Import PhoneNotices
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📞 Importing PhoneNotices...');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            $phoneResults = $phoneNoticeImporter->importFromFTP(
                function ($current, $total, $filename = null, $isNewFile = false) {
                    // Display filename when starting a new file
                    if ($isNewFile && $filename) {
                        $this->newLine();
                        $this->line("   📄 Importing: <comment>{$filename}</comment>");
                    }
                    
                    // Display progress for current file
                    if (!$isNewFile && $current > 0 && $total > 0) {
                        if ($current % 100 === 0 || $current === $total) {
                            $this->output->write("\r   Processing: {$current}/{$total}");
                        }
                    }
                },
                $startDate,
                $endDate
            );

            $this->newLine();

            if (!empty($phoneResults['files_processed'])) {
                $this->info("   ✅ Imported {$phoneResults['imported']} records");
                $this->line("   Files: " . implode(', ', $phoneResults['files_processed']));
            } else {
                $this->warn("   ⚠️  No PhoneNotices files found");
            }

            $results['phone_notices'] = [
                'imported' => $phoneResults['imported'],
                'errors' => $phoneResults['errors'],
            ];
        } catch (\Exception $e) {
            $this->error("   ❌ PhoneNotices import failed: {$e->getMessage()}");
            $results['phone_notices']['errors']++;
        }

        $this->newLine();

        // Step 2: Import Shoutbomb Submissions
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📋 Importing Shoutbomb Submissions...');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            if ($this->option('all') || ($startDate && $endDate && !$startDate->isSameDay($endDate))) {
                // Import range or all - with callback
                $submissionResults = $submissionImporter->importAllFromFTP(
                    $startDate, 
                    $endDate,
                    function ($current, $total, $filename = null, $isNewFile = false) {
                        if ($isNewFile && $filename) {
                            $this->newLine();
                            $this->line("   📄 Importing: <comment>{$filename}</comment>");
                        }
                        if (!$isNewFile && $current > 0 && $total > 0) {
                            if ($current % 500 === 0 || $current === $total) {
                                $this->output->write("\r   Processing: {$current}/{$total}");
                            }
                        }
                    }
                );

                $totals = $submissionResults['totals'] ?? [];
                $dates = $submissionResults['dates'] ?? [];

                $this->newLine();

                if (!empty($dates)) {
                    $this->info("   ✅ Processed " . count($dates) . " date(s)");
                    foreach ($dates as $d) {
                        $this->line("      - {$d}");
                    }
                } else {
                    $this->warn("   ⚠️  No submission files found");
                }

                $results['submissions'] = [
                    'holds' => $totals['holds'] ?? 0,
                    'overdues' => $totals['overdues'] ?? 0,
                    'renewals' => $totals['renewals'] ?? 0,
                    'voice_patrons' => $totals['voice_patrons'] ?? 0,
                    'text_patrons' => $totals['text_patrons'] ?? 0,
                    'errors' => $totals['errors'] ?? 0,
                ];
            } else {
                // Single date import - with callback
                $importDate = $startDate ?? now();
                $submissionResults = $submissionImporter->importFromFTP(
                    $importDate,
                    function ($current, $total, $filename = null, $isNewFile = false) {
                        if ($isNewFile && $filename) {
                            $this->newLine();
                            $this->line("   📄 Importing: <comment>{$filename}</comment>");
                        }
                        if (!$isNewFile && $current > 0 && $total > 0) {
                            if ($current % 500 === 0 || $current === $total) {
                                $this->output->write("\r   Processing: {$current}/{$total}");
                            }
                        }
                    }
                );

                $this->newLine();

                $results['submissions'] = [
                    'holds' => $submissionResults['holds'] ?? 0,
                    'overdues' => $submissionResults['overdues'] ?? 0,
                    'renewals' => $submissionResults['renewals'] ?? 0,
                    'voice_patrons' => $submissionResults['voice_patrons'] ?? 0,
                    'text_patrons' => $submissionResults['text_patrons'] ?? 0,
                    'errors' => $submissionResults['errors'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Submissions import failed: {$e->getMessage()}");
            $results['submissions']['errors']++;
        }

        $this->newLine();

        // Summary
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Import Summary');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->table(
            ['Category', 'Count'],
            [
                ['PhoneNotices', $results['phone_notices']['imported']],
                ['Holds', $results['submissions']['holds']],
                ['Overdues', $results['submissions']['overdues']],
                ['Renewals', $results['submissions']['renewals']],
                ['Voice Patrons', $results['submissions']['voice_patrons']],
                ['Text Patrons', $results['submissions']['text_patrons']],
                ['Errors', $results['phone_notices']['errors'] + $results['submissions']['errors']],
            ]
        );

        $this->newLine();

        $totalErrors = $results['phone_notices']['errors'] + $results['submissions']['errors'];

        if ($totalErrors > 0) {
            $this->warn("⚠️  Completed with {$totalErrors} error(s)");
            return Command::FAILURE;
        }

        $this->info('✅ FTP Files Import completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Resolve the desired date range based on options.
     */
    protected function resolveDateRange(): array
    {
        // --all flag: return nulls to import everything
        if ($this->option('all')) {
            return [null, null];
        }

        // Explicit date range
        if ($this->option('start-date') || $this->option('end-date')) {
            $startDate = $this->option('start-date')
                ? Carbon::parse($this->option('start-date'))->startOfDay()
                : now()->startOfDay();

            $endDate = $this->option('end-date')
                ? Carbon::parse($this->option('end-date'))->endOfDay()
                : now()->endOfDay();

            return [$startDate, $endDate];
        }

        // Days back option
        if ($this->option('days')) {
            $days = (int) $this->option('days');
            return [
                now()->subDays($days)->startOfDay(),
                now()->endOfDay(),
            ];
        }

        // Default: today only
        return [
            now()->startOfDay(),
            now()->endOfDay(),
        ];
    }
}
