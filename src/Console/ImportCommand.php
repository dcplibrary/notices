<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Contracts\NotificationFailureDetectorInterface;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\IngestedEmail;
use Dcplibrary\Notices\Models\JobRun;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Models\PolarisOverdueNotification;
use Dcplibrary\Notices\Models\PolarisRenewalNotification;
use Dcplibrary\Notices\NoticesManager;
use Dcplibrary\Notices\Services\JobRunManager;
use Dcplibrary\Notices\Services\NotificationActivityLogger;
use Dcplibrary\Notices\Support\Progress\ConsoleProgressSink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Artisan command to run the full notices pipeline for a given date.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * CRITICAL: Execution order MUST be maintained to prevent false positives!
 *
 * Pipeline Order (DO NOT CHANGE):
 * 1. Import FTP exports (Polaris notifications)
 * 2. Import email confirmations (LX Starter delivery status)
 * 3. Deduplicate notifications
 * 4. Reconcile (match confirmations to notifications, update status)
 * 5. Process orphaned (mark STILL-PENDING as orphaned/failed)
 * 6. Run integrity checks (validate data, auto-fix issues)
 *
 * If you mark notifications as orphaned BEFORE reconciliation runs, you will
 * get false positives because reconciliation hasn't had a chance to update
 * their status yet!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * ## What This Command Does
 *
 * This is the main entry point for the notification import/reconciliation pipeline.
 * It orchestrates all import commands and ensures they run in the correct order:
 *
 * 1. **Import FTP Exports** (via `notices:import-ftp`)
 *    - Imports Polaris notifications from FTP exports
 *    - Creates notification_import_batch records
 *
 * 2. **Import Email Confirmations** (via `notices:import-email`)
 *    - Imports delivery confirmations from LX Starter email reports
 *    - Creates delivery_confirmation records
 *
 * 3. **Deduplicate & Reconcile**
 *    - Groups raw exports into logical notifications
 *    - Matches delivery confirmations to notifications
 *    - Updates notification status (pending → delivered/failed)
 *
 * 4. **Process Orphaned Notifications** (via `notices:process-orphaned`)
 *    - Marks notifications as orphaned ONLY if still pending AFTER reconciliation
 *    - Uses import-batch-based detection (NOT time-based)
 *
 * 5. **Integrity Checks** (via `notices:integrity-check --fix`)
 *    - Validates data consistency
 *    - Auto-fixes common issues
 *    - Includes Check 9: Orphaned without import batch
 *
 * ## Usage
 *
 * ```bash
 * # Import today's data
 * php artisan notices:import
 *
 * # Import specific date
 * php artisan notices:import --date=2026-01-01
 *
 * # Import date range
 * php artisan notices:import --from=2026-01-01 --to=2026-01-07
 *
 * # Import last N days
 * php artisan notices:import --days=7
 * ```
 *
 * @see ProcessOrphanedNotificationsCommand (line 128)
 * @see IntegrityCheckCommand (line 133)
 */
class ImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Uses the same date option pattern as other import commands.
     *
     * @var string
     */
    protected $signature = 'notices:import
                            {--date= : Date (YYYY-MM-DD) to operate on}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to=   : End date (YYYY-MM-DD)}
                            {--days= : Number of days back from today (inclusive)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the full notices pipeline (import FTP, import emails, deduplicate, reconcile) for a given date.';

    /**
     * Execute the console command.
     */
    public function handle(
        NoticesManager $manager,
        NotificationFailureDetectorInterface $failureDetector,
        NotificationActivityLogger $activityLogger,
        JobRunManager $jobRunManager
    ): int {
        $dateOpt = $this->option('date');
        $fromOpt = $this->option('from');
        $toOpt   = $this->option('to');
        $daysOpt = $this->option('days');

        // Resolve date range using the same rules as notices:import-ftp.
        [$fromDate, $toDate, $warnings] = $this->resolveDateRange(
            $dateOpt,
            $fromOpt,
            $toOpt,
            $daysOpt,
            false,
        );

        // Create a JobRun record for this notices:import execution.
        $jobRun = $jobRunManager->start(
            'notices:import',
            [
                'options' => $this->options(),
            ],
            'manual'
        );

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        // Default to today if nothing resolved.
        if (!$fromDate || !$toDate) {
            $today    = new \DateTimeImmutable('today');
            $fromDate = $today;
            $toDate   = $today;
        }

        $dates = $this->expandDateRange($fromDate, $toDate);

        $label = $fromDate->format('Y-m-d') . ($fromDate == $toDate ? '' : ' to ' . $toDate->format('Y-m-d'));
        $this->info('[notices:import] Running full notices import for ' . $label);

        // High-level batch log for this notices:import run (legacy import_batches table)
        $batch = NotificationImportBatch::create([
            'source'        => 'notices_pipeline',
            'date_for'      => $fromDate->format('Y-m-d'),
            'imported_at'   => now(),
            'identifier'    => $label,
            'records_count' => 0,
        ]);

        // Console progress sink for per-phase indicators
        $sink = new ConsoleProgressSink($this->output);

        foreach ($dates as $date) {
            // Start progress table for this day
            $sink->startDay($date);

            // 1) Import raw data from all sources (FTP exports + NotificationLog/History)
            $sink->step('ftp', 'pending');
            $manager->importShoutbombExports($date);
            $manager->importPolarisPhoneNotices($date);
            $manager->importNotificationLog($date);
            $manager->importNotificationHistory($date);

            // Collect per-file details (best-effort) for this date
            $ftpFiles = [];

            // Holds - group by source_filename so multiple daily exports show separately
            $holdsQuery = PolarisHoldNotification::whereDate('export_datetime', $date->format('Y-m-d'));
            if (Schema::hasColumn('polaris_hold_notifications', 'source_filename')) {
                $holdsGroups = $holdsQuery
                    ->selectRaw('COALESCE(source_filename, "holds (no filename)") as label, COUNT(*) as count')
                    ->groupBy('label')
                    ->orderBy('label')
                    ->get()
                ;

                foreach ($holdsGroups as $group) {
                    $ftpFiles[] = [
                        'label' => $group->label,
                        'count' => $group->count,
                    ];
                }
            } else {
                $holdsCount = $holdsQuery->count();
                if ($holdsCount > 0) {
                    $ftpFiles[] = [
                        'label' => 'holds (no filename)',
                        'count' => $holdsCount,
                    ];
                }
            }

            // Renewals - group by source_filename as well
            $renewQuery = PolarisRenewalNotification::whereDate('export_datetime', $date->format('Y-m-d'));
            if (Schema::hasColumn('polaris_renewal_notifications', 'source_filename')) {
                $renewGroups = $renewQuery
                    ->selectRaw('COALESCE(source_filename, "renew (no filename)") as label, COUNT(*) as count')
                    ->groupBy('label')
                    ->orderBy('label')
                    ->get()
                ;

                foreach ($renewGroups as $group) {
                    $ftpFiles[] = [
                        'label' => $group->label,
                        'count' => $group->count,
                    ];
                }
            } else {
                $renewCount = $renewQuery->count();
                if ($renewCount > 0) {
                    $ftpFiles[] = [
                        'label' => 'renew (no filename)',
                        'count' => $renewCount,
                    ];
                }
            }

            // Overdues - show one row per file when trace columns are available
            $overdueQuery = PolarisOverdueNotification::whereDate('export_datetime', $date->format('Y-m-d'));
            if (Schema::hasColumn('polaris_overdue_notifications', 'source_filename')) {
                $overdueGroups = $overdueQuery
                    ->selectRaw('COALESCE(source_filename, "overdue (no filename)") as label, COUNT(*) as count')
                    ->groupBy('label')
                    ->orderBy('label')
                    ->get()
                ;

                foreach ($overdueGroups as $group) {
                    $ftpFiles[] = [
                        'label' => $group->label,
                        'count' => $group->count,
                    ];
                }
            } else {
                $overdueCount = $overdueQuery->count();
                if ($overdueCount > 0) {
                    $ftpFiles[] = [
                        'label' => 'overdue (no filename)',
                        'count' => $overdueCount,
                    ];
                }
            }

            // NotificationLog upload confirmations
            $logCount = DeliveryConfirmation::query()
                ->where('confirmation_source', 'notification_log')
                ->whereDate('notice_date', $date->format('Y-m-d'))
                ->count()
            ;
            $logFile = DeliveryConfirmation::query()
                ->where('confirmation_source', 'notification_log')
                ->whereDate('notice_date', $date->format('Y-m-d'))
                ->orderBy('notice_date')
                ->value('source_filename')
            ;
            if ($logCount > 0) {
                $ftpFiles[] = [
                    'label' => $logFile ?: 'notification_log (no filename)',
                    'count' => $logCount,
                ];
            }

            $sink->step('ftp', 'done', ['files' => $ftpFiles]);

            // 2) Ingest failure and summary emails
            $sink->step('email', 'pending');
            $manager->ingestFailureAndSummaryEmails($date);

            // Show which email reports were ingested for this date
            $emailFiles = IngestedEmail::query()
                ->whereDate('received_at', $date->format('Y-m-d'))
                ->orderBy('id')
                ->get(['subject', 'parsed_record_count'])
            ;

            $emailLines = [];
            foreach ($emailFiles as $email) {
                $label = $email->subject ?: '(no subject)';
                if (mb_strlen($label) > 60) {
                    $label = mb_substr($label, 0, 57) . '…';
                }
                $emailLines[] = [
                    'label' => $label,
                    'count' => $email->parsed_record_count ?? 0,
                ];
            }

            $sink->step('email', 'done', ['files' => $emailLines]);

            // Record per-source import batches so the dashboard Last Import Status
            // card can see that FTP and report imports have run, even when the
            // full notices:import pipeline is used instead of notices:import-ftp.
            foreach (['ftp_holds', 'ftp_renew', 'ftp_overdue', 'phone_notices', 'notification_log', 'notification_history', 'email_reports'] as $sourceKey) {
                NotificationImportBatch::updateOrCreate(
                    [
                        'source'   => $sourceKey,
                        'date_for' => $date->format('Y-m-d'),
                    ],
                    [
                        'imported_at' => now(),
                        'identifier'  => $sourceKey . '_' . $date->format('Y-m-d'),
                        // We don’t yet break counts down per source here; the
                        // dashboard only needs last_import/recency for status.
                        'records_count' => \DB::table('notifications')
                            ->whereDate('first_exported_at', $date->format('Y-m-d'))
                            ->count(),
                    ]
                );
            }

            // 3) Deduplicate and reconcile
            $sink->step('reconcile', 'pending', ['notes' => 'running']);
            $manager->deduplicateNotifications($date);
            $manager->reconcileStatuses($date);
            $sink->step('reconcile', 'done');

            // 4) Detect failures and invalid phones
            $sink->step('failures', 'pending', ['notes' => 'detecting']);
            $manager->detectNotificationFailures($date);
            $failureDetector->detectInvalidPhoneNumbers($date);

            // Compute per-status counts for this date
            $statusCounts = Notification::query()
                ->whereDate('first_exported_at', $date->format('Y-m-d'))
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray()
            ;

            $stats = [
                'delivered' => $statusCounts['delivered'] ?? 0,
                'failed'    => $statusCounts['failed']    ?? 0,
                'pending'   => $statusCounts['pending']   ?? 0,
                'total'     => array_sum($statusCounts),
            ];

            $sink->step('failures', 'done', ['stats' => $stats]);

            // Close progress output for this day
            $sink->endDay($date);
        }

        // After processing all dates, check for orphaned notifications and mark them as failed
        $this->line('');
        $this->line('--> Processing orphaned notifications...');
        $this->call('notices:process-orphaned', ['--days' => 7]);

        // Run integrity check to validate data and auto-fix common issues
        $this->line('');
        $this->line('--> Running integrity checks...');
        $this->call('notices:integrity-check', ['--days' => 30, '--fix' => true]);

        $this->line('');
        $this->info('[notices:import] Done.');

        // Mark job run complete
        $jobRunManager->finish($jobRun, 'success');

        return self::SUCCESS;
    }

    /**
     * Resolve a date range based on the given options.
     *
     * Returns [fromDate|null, toDate|null, warnings[]].
     */
    protected function resolveDateRange(?string $date, ?string $from, ?string $to, ?string $days, bool $isList): array
    {
        $warnings = [];

        $fromDate = null;
        $toDate   = null;

        // Highest precedence: explicit --date
        if ($date) {
            try {
                $fromDate = new \DateTimeImmutable($date);
                $toDate   = $fromDate;

                if ($from || $to || $days) {
                    $warnings[] = 'Ignoring --from/--to/--days because --date was provided.';
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --date value: ' . $date;
            }

            return [$fromDate, $toDate, $warnings];
        }

        // Next: explicit from/to range
        if ($from || $to) {
            try {
                if ($from) {
                    $fromDate = new \DateTimeImmutable($from);
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --from value: ' . $from;
            }

            try {
                if ($to) {
                    $toDate = new \DateTimeImmutable($to);
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Invalid --to value: ' . $to;
            }

            // If only from is provided, default to today
            if ($fromDate && !$toDate) {
                $toDate = new \DateTimeImmutable('today');
            }

            // If only to is provided, treat as single-day range
            if (!$fromDate && $toDate) {
                $fromDate = $toDate;
            }

            if ($days) {
                $warnings[] = 'Ignoring --days because --from/--to were provided.';
            }

            return [$fromDate, $toDate, $warnings];
        }

        // Next: relative range via --days
        if (null !== $days && '' !== $days) {
            $n = (int) $days;
            if ($n <= 0) {
                $warnings[] = 'Ignoring --days because it must be a positive integer.';

                return [$fromDate, $toDate, $warnings];
            }

            $toDate   = new \DateTimeImmutable('today');
            $fromDate = $toDate->sub(new \DateInterval('P' . ($n - 1) . 'D'));

            return [$fromDate, $toDate, $warnings];
        }

        // If we're listing and no dates are provided, return nulls (list all matching files).
        if ($isList) {
            return [null, null, $warnings];
        }

        // In import mode, if nothing specified, we will default to today in handle().
        return [null, null, $warnings];
    }

    /**
     * Expand a [from, to] range (inclusive) into an array of DateTimeImmutable days.
     */
    protected function expandDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $dates   = [];
        $current = $from;

        while ($current <= $to) {
            $dates[] = $current;
            $current = $current->add(new \DateInterval('P1D'));
        }

        return $dates;
    }
}
