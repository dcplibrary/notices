<?php

namespace Dcplibrary\Notices\Console\Commands;

use Dcplibrary\Notices\Services\PolarisPhoneNoticeImporter;
use Dcplibrary\Notices\Services\ShoutbombSubmissionImporter;
use Dcplibrary\Notices\Services\PatronDeliveryPreferenceImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Import all FTP files (PhoneNotices + Shoutbomb submissions + Patron preferences)
 *
 * This streamlined command imports:
 * - PhoneNotices files (PhoneNotices.csv or PhoneNotices_YYYY-MM-DD_HH-MM-SS.txt)
 * - Shoutbomb submission files (holds, overdue, renew)
 * - Patron delivery preferences (voice_patrons, text_patrons) - OPTIONAL
 */
class ImportFTPFiles extends Command
{
    protected $signature = 'notices:import-ftp-files
                            {--start-date= : Start date (Y-m-d), defaults to today}
                            {--end-date= : End date (Y-m-d), defaults to today}
                            {--days= : Number of days back to import (alternative to date range)}
                            {--all : Import all available files regardless of date}
                            {--import-patrons : Also import patron delivery preferences (voice/text)}';

    protected $description = 'Import all FTP files (PhoneNotices, Submissions, and optionally Patrons)';

    public function handle(
        PolarisPhoneNoticeImporter $phoneNoticeImporter,
        ShoutbombSubmissionImporter $submissionImporter,
        PatronDeliveryPreferenceImporter $patronImporter
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
        
        if ($this->option('import-patrons')) {
            $this->line("👥 Patron import: <info>ENABLED</info>");
        } else {
            $this->line("👥 Patron import: <comment>DISABLED</comment> (use --import-patrons to enable)");
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
            'patrons' => [
                'voice_total' => 0,
                'text_total' => 0,
                'voice_new' => 0,
                'text_new' => 0,
                'voice_changed' => 0,
                'text_changed' => 0,
                'voice_skipped' => false,
                'text_skipped' => false,
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
                    if ($isNewFile && $filename) {
                        $this->newLine();
                        $this->line("   📄 Importing: <comment>{$filename}</comment>");
                    }
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

        // Step 2: Import Patron Delivery Preferences (OPTIONAL)
        if ($this->option('import-patrons')) {
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('👥 Importing Patron Delivery Preferences...');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            try {
                if ($this->option('all') || ($startDate && $endDate && !$startDate->isSameDay($endDate))) {
                    // Import range
                    $patronResults = $patronImporter->importAllFromFTP(
                        $startDate,
                        $endDate,
                        function ($current, $total, $filename = null, $isNewFile = false, $skipped = false) {
                            if ($isNewFile && $filename) {
                                $this->newLine();
                                if ($skipped) {
                                    $this->line("   ⏭️  Skipping (already processed): <comment>{$filename}</comment>");
                                } else {
                                    $this->line("   📄 Importing: <comment>{$filename}</comment>");
                                }
                            }
                            if (!$isNewFile && !$skipped && $current > 0 && $total > 0) {
                                if ($current % 1000 === 0 || $current === $total) {
                                    $this->output->write("\r   Processing: {$current}/{$total}");
                                }
                            }
                        }
                    );

                    $totals = $patronResults['totals'] ?? [];
                    $this->newLine();
                    $this->info("   ✅ Voice: {$totals['voice_patrons']} ({$totals['voice_new']} new, {$totals['voice_changed']} changed)");
                    $this->info("   ✅ Text: {$totals['text_patrons']} ({$totals['text_new']} new, {$totals['text_changed']} changed)");

                    $results['patrons'] = [
                        'voice_total' => $totals['voice_patrons'],
                        'text_total' => $totals['text_patrons'],
                        'voice_new' => $totals['voice_new'],
                        'text_new' => $totals['text_new'],
                        'voice_changed' => $totals['voice_changed'],
                        'text_changed' => $totals['text_changed'],
                        'errors' => $totals['errors'] ?? 0,
                    ];
                } else {
                    // Single date
                    $importDate = $startDate ?? now();
                    $patronResults = $patronImporter->importFromFTP(
                        $importDate,
                        function ($current, $total, $filename = null, $isNewFile = false, $skipped = false) {
                            if ($isNewFile && $filename) {
                                $this->newLine();
                                if ($skipped) {
                                    $this->line("   ⏭️  Skipping (already processed): <comment>{$filename}</comment>");
                                } else {
                                    $this->line("   📄 Importing: <comment>{$filename}</comment>");
                                }
                            }
                            if (!$isNewFile && !$skipped && $current > 0 && $total > 0) {
                                if ($current % 1000 === 0 || $current === $total) {
                                    $this->output->write("\r   Processing: {$current}/{$total}");
                                }
                            }
                        }
                    );

                    $this->newLine();
                    
                    if ($patronResults['voice_skipped']) {
                        $this->line("   ⏭️  Voice patrons: <comment>Skipped (already processed)</comment>");
                    } else {
                        $this->info("   ✅ Voice: {$patronResults['voice_patrons']} ({$patronResults['voice_new']} new, {$patronResults['voice_changed']} changed)");
                    }

                    if ($patronResults['text_skipped']) {
                        $this->line("   ⏭️  Text patrons: <comment>Skipped (already processed)</comment>");
                    } else {
                        $this->info("   ✅ Text: {$patronResults['text_patrons']} ({$patronResults['text_new']} new, {$patronResults['text_changed']} changed)");
                    }

                    $results['patrons'] = $patronResults;
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Patron import failed: {$e->getMessage()}");
                $results['patrons']['errors']++;
            }

            $this->newLine();
        }

        // Step 3: Import Shoutbomb Submissions
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📋 Importing Shoutbomb Submissions...');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            if ($this->option('all') || ($startDate && $endDate && !$startDate->isSameDay($endDate))) {
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

        $summaryData = [
            ['PhoneNotices', $results['phone_notices']['imported']],
            ['Holds', $results['submissions']['holds']],
            ['Overdues', $results['submissions']['overdues']],
            ['Renewals', $results['submissions']['renewals']],
        ];

        if ($this->option('import-patrons')) {
            $summaryData[] = ['Voice Patrons (New)', $results['patrons']['voice_new']];
            $summaryData[] = ['Text Patrons (New)', $results['patrons']['text_new']];
        }

        $totalErrors = $results['phone_notices']['errors'] 
            + $results['submissions']['errors'] 
            + ($this->option('import-patrons') ? $results['patrons']['errors'] : 0);

        $summaryData[] = ['Errors', $totalErrors];

        $this->table(['Category', 'Count'], $summaryData);

        $this->newLine();

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
        if ($this->option('all')) {
            return [null, null];
        }

        if ($this->option('start-date') || $this->option('end-date')) {
            $startDate = $this->option('start-date')
                ? Carbon::parse($this->option('start-date'))->startOfDay()
                : now()->startOfDay();

            $endDate = $this->option('end-date')
                ? Carbon::parse($this->option('end-date'))->endOfDay()
                : now()->endOfDay();

            return [$startDate, $endDate];
        }

        if ($this->option('days')) {
            $days = (int) $this->option('days');
            return [
                now()->subDays($days)->startOfDay(),
                now()->endOfDay(),
            ];
        }

        return [
            now()->startOfDay(),
            now()->endOfDay(),
        ];
    }
}
