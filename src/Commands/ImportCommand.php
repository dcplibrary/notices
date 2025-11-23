<?php

namespace Dcplibrary\Notices\Commands;

use Dcplibrary\Notices\Services\NotificationImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Simplified unified import command for all notification data.
 *
 * Consolidates imports from:
 *   - Patron lists (voice_patrons.txt, text_patrons.txt)
 *   - PhoneNotices.csv (validation baseline)
 *   - Notification exports (holds.txt, overdue.txt, renew.txt)
 *   - Email failure reports
 *
 * Import Order (CRITICAL):
 *   1. Patron lists (determines delivery_option_id)
 *   2. PhoneNotices.csv (validation baseline)
 *   3. Notification exports (enriched with patron list data)
 *   4. Failure reports (enriched with PhoneNotices data)
 */
class ImportCommand extends Command
{
    protected $signature = 'notices:import
                            {--date= : Import data for a specific date (Y-m-d)}
                            {--start= : Start date for range import (Y-m-d)}
                            {--end= : End date for range import (Y-m-d)}
                            {--days=1 : Number of days back to import (default: 1)}
                            {--all : Import ALL available historical data from FTP}
                            {--type= : Import specific type only (patron-lists, phone-notices, holds, overdue, renew, failures)}
                            {--skip-enrichment : Skip the enrichment step}
                            {--dry-run : Show what would be imported without actually importing}';

    protected $description = 'Import notification data from FTP exports and email reports';

    protected NotificationImportService $importService;

    public function __construct(NotificationImportService $importService)
    {
        parent::__construct();
        $this->importService = $importService;
    }

    public function handle(): int
    {
        $this->displayBanner();

        // Determine date range
        $dateRange = $this->determineDateRange();
        if (!$dateRange) {
            return Command::FAILURE;
        }

        [$startDate, $endDate] = $dateRange;
        $isDryRun = $this->option('dry-run');
        $specificType = $this->option('type');

        $this->info("Import period: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be imported');
        }
        $this->newLine();

        $results = [
            'patron_lists' => ['voice' => 0, 'text' => 0],
            'phone_notices' => 0,
            'holds' => 0,
            'overdue' => 0,
            'renew' => 0,
            'failures' => 0,
            'enriched' => 0,
            'errors' => [],
        ];

        // Step 1: Import patron lists (MUST BE FIRST)
        if (!$specificType || $specificType === 'patron-lists') {
            $this->importPatronLists($startDate, $endDate, $isDryRun, $results);
        }

        // Step 2: Import PhoneNotices.csv
        if (!$specificType || $specificType === 'phone-notices') {
            $this->importPhoneNotices($startDate, $endDate, $isDryRun, $results);
        }

        // Step 3: Import notification exports
        if (!$specificType || in_array($specificType, ['holds', 'overdue', 'renew', null])) {
            $this->importNotificationExports($startDate, $endDate, $isDryRun, $specificType, $results);
        }

        // Step 4: Import failure reports
        if (!$specificType || $specificType === 'failures') {
            $this->importFailureReports($startDate, $endDate, $isDryRun, $results);
        }

        // Step 5: Run enrichment
        if (!$this->option('skip-enrichment') && !$isDryRun) {
            $this->runEnrichment($results);
        }

        // Display summary
        $this->displaySummary($results);

        return empty($results['errors']) ? Command::SUCCESS : Command::FAILURE;
    }

    protected function displayBanner(): void
    {
        $this->newLine();
        $this->info('  ╔═══════════════════════════════════════════════╗');
        $this->info('  ║      Notification Data Import                 ║');
        $this->info('  ╚═══════════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function determineDateRange(): ?array
    {
        // --all flag: get all available files from FTP
        if ($this->option('all')) {
            $this->info('Scanning FTP for all available historical data...');
            $range = $this->importService->getAvailableDateRange();
            if (!$range) {
                $this->error('Could not determine date range from FTP files');
                return null;
            }
            $this->info("Found data from {$range['start']->format('Y-m-d')} to {$range['end']->format('Y-m-d')}");
            return [$range['start'], $range['end']];
        }

        // --date flag: specific single date
        if ($date = $this->option('date')) {
            $targetDate = Carbon::parse($date);
            return [$targetDate, $targetDate];
        }

        // --start/--end flags: date range
        if ($this->option('start') || $this->option('end')) {
            $startDate = $this->option('start') ? Carbon::parse($this->option('start')) : now()->subDays(30);
            $endDate = $this->option('end') ? Carbon::parse($this->option('end')) : now();
            return [$startDate, $endDate];
        }

        // --days flag: relative days back
        $days = (int) $this->option('days');
        $endDate = now();
        $startDate = now()->subDays($days - 1);
        return [$startDate, $endDate];
    }

    protected function importPatronLists(Carbon $startDate, Carbon $endDate, bool $isDryRun, array &$results): void
    {
        $this->line('→ Step 1: Importing patron lists...');
        $this->line('  (Determines delivery_option_id for notifications)');

        try {
            if ($isDryRun) {
                $files = $this->importService->listPatronListFiles($startDate, $endDate);
                $this->line("  Would import {$files['voice_count']} voice files, {$files['text_count']} text files");
                return;
            }

            $imported = $this->importService->importPatronLists($startDate, $endDate);
            $results['patron_lists'] = $imported;
            $this->info("  ✓ Imported {$imported['voice']} voice patrons, {$imported['text']} text patrons");

        } catch (\Exception $e) {
            $this->error("  ✗ Failed: {$e->getMessage()}");
            $results['errors'][] = "Patron lists: {$e->getMessage()}";
        }

        $this->newLine();
    }

    protected function importPhoneNotices(Carbon $startDate, Carbon $endDate, bool $isDryRun, array &$results): void
    {
        $this->line('→ Step 2: Importing PhoneNotices.csv (validation baseline)...');

        try {
            if ($isDryRun) {
                $files = $this->importService->listPhoneNoticeFiles($startDate, $endDate);
                $this->line("  Would import {$files['count']} PhoneNotices files");
                return;
            }

            $imported = $this->importService->importPhoneNotices($startDate, $endDate);
            $results['phone_notices'] = $imported;
            $this->info("  ✓ Imported {$imported} phone notice records");

        } catch (\Exception $e) {
            $this->error("  ✗ Failed: {$e->getMessage()}");
            $results['errors'][] = "PhoneNotices: {$e->getMessage()}";
        }

        $this->newLine();
    }

    protected function importNotificationExports(
        Carbon $startDate,
        Carbon $endDate,
        bool $isDryRun,
        ?string $specificType,
        array &$results
    ): void {
        $this->line('→ Step 3: Importing notification exports...');

        $types = $specificType ? [$specificType] : ['holds', 'overdue', 'renew'];

        foreach ($types as $type) {
            try {
                if ($isDryRun) {
                    $files = $this->importService->listNotificationFiles($type, $startDate, $endDate);
                    $this->line("  {$type}: Would import {$files['count']} files");
                    continue;
                }

                $imported = $this->importService->importNotifications($type, $startDate, $endDate);
                $results[$type] = $imported;
                $this->info("  ✓ {$type}: Imported {$imported} records");

            } catch (\Exception $e) {
                $this->error("  ✗ {$type}: {$e->getMessage()}");
                $results['errors'][] = "{$type}: {$e->getMessage()}";
            }
        }

        $this->newLine();
    }

    protected function importFailureReports(Carbon $startDate, Carbon $endDate, bool $isDryRun, array &$results): void
    {
        $this->line('→ Step 4: Importing failure reports from email...');

        try {
            if ($isDryRun) {
                $this->line('  Would import failure reports from email');
                return;
            }

            $imported = $this->importService->importFailureReports($startDate, $endDate);
            $results['failures'] = $imported;
            $this->info("  ✓ Imported {$imported} failure reports");

        } catch (\Exception $e) {
            $this->error("  ✗ Failed: {$e->getMessage()}");
            $results['errors'][] = "Failures: {$e->getMessage()}";
        }

        $this->newLine();
    }

    protected function runEnrichment(array &$results): void
    {
        $this->line('→ Step 5: Running enrichment...');
        $this->line('  - Enriching notification exports with delivery_option_id from patron lists');
        $this->line('  - Enriching overdue records with notification_type_id from PhoneNotices');
        $this->line('  - Linking failures to PhoneNotices');

        try {
            $enriched = $this->importService->runEnrichment();
            $results['enriched'] = $enriched;
            $this->info("  ✓ Enriched {$enriched['notifications']} notifications, {$enriched['failures']} failures");

        } catch (\Exception $e) {
            $this->error("  ✗ Enrichment failed: {$e->getMessage()}");
            $results['errors'][] = "Enrichment: {$e->getMessage()}";
        }

        $this->newLine();
    }

    protected function displaySummary(array $results): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Import Summary');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $this->table(
            ['Data Type', 'Records Imported'],
            [
                ['Voice Patrons', number_format($results['patron_lists']['voice'] ?? 0)],
                ['Text Patrons', number_format($results['patron_lists']['text'] ?? 0)],
                ['PhoneNotices', number_format($results['phone_notices'] ?? 0)],
                ['Holds', number_format($results['holds'] ?? 0)],
                ['Overdue', number_format($results['overdue'] ?? 0)],
                ['Renewals', number_format($results['renew'] ?? 0)],
                ['Failures', number_format($results['failures'] ?? 0)],
            ]
        );

        if (!empty($results['enriched'])) {
            $this->newLine();
            $this->line('Enrichment:');
            $this->line("  Notifications enriched: {$results['enriched']['notifications']}");
            $this->line("  Failures enriched: {$results['enriched']['failures']}");
        }

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        $this->newLine();
        if (empty($results['errors'])) {
            $this->info('✓ Import completed successfully!');
        } else {
            $this->warn('⚠ Import completed with errors');
        }
        $this->newLine();
    }
}
