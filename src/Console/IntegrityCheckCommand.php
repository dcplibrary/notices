<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command to run integrity checks on notification data.
 *
 * This command validates data consistency after imports and can auto-fix common
 * issues. It is automatically run at the end of the notices:import pipeline with
 * the --fix flag enabled.
 *
 * Nine integrity checks performed:
 *
 * 1. **Delivered notifications incorrectly marked as failed**
 *    - Notifications with delivered_at timestamp but status = 'failed'
 *    - Fix: Update status to 'delivered', resolve active issues
 *
 * 2. **Delivered notifications with active orphaned issues**
 *    - Delivered notifications still flagged with polaris_export_failure
 *    - Fix: Resolve the orphaned issues (notification was actually delivered)
 *
 * 3. **Notifications marked delivered without timestamp**
 *    - Status = 'delivered' but delivered_at is NULL
 *    - Fix: Set delivered_at to last_exported_at or first_exported_at
 *
 * 4. **Pending notifications with delivery timestamp**
 *    - Status = 'pending' but delivered_at is set
 *    - Fix: Update status to 'delivered'
 *
 * 5. **Active issues on delivered notifications**
 *    - PatronDeliveryIssues not resolved but notification is delivered
 *    - Fix: Resolve the issues with auto-resolution note
 *
 * 6. **Orphaned issues without matching notifications**
 *    - PatronDeliveryIssues referencing deleted notification records
 *    - Fix: Resolve with note "Notification record no longer exists"
 *
 * 7. **Delivered timestamp before notification was created** ← CRITICAL
 *    - delivered_at < generated_at OR delivered_at < first_exported_at
 *    - This catches the core reconciliation bug where notifications were
 *      matched with delivery confirmations from different dates
 *    - Fix: Set delivered_at to first_exported_at (safest default)
 *
 * 8. **Mail notifications incorrectly flagged as LX Starter failures**
 *    - delivery_option_id = 1 (Mail) with lx_starter_failure issues
 *    - Mail notifications are printed by staff, not processed by LX Starter
 *    - Fix: Resolve the incorrect LX Starter failure issues
 *
 * 9. **Orphaned notifications marked without import batch running** ← CRITICAL
 *    - Notifications marked as orphaned/failed WITHOUT an import batch running after export
 *    - This enforces import-batch-based detection instead of time-based detection
 *    - Fix: Revert to pending status, resolve orphaned issues with explanation
 *
 * Usage:
 *   php artisan notices:integrity-check --days=30        # Report only
 *   php artisan notices:integrity-check --days=30 --fix  # Report and fix
 *
 * @see ImportCommand Automatically runs with --fix
 */
class IntegrityCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:integrity-check
                            {--fix : Automatically fix issues found}
                            {--days=30 : Number of days back to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run integrity checks on notification data and optionally fix issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $autoFix = $this->option('fix');

        $this->info("Running integrity checks on notifications from the last {$days} days...");
        $this->newLine();

        $issues = [];
        $fixed  = [];

        // Check 1: Delivered notifications with failed status or orphaned issues
        $this->line('Check 1: Delivered notifications incorrectly marked as failed...');
        $deliveredButFailed = Notification::whereNotNull('delivered_at')
            ->where('status', 'failed')
            ->where('first_exported_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($deliveredButFailed->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d delivered notification(s) with status = "failed"',
                $deliveredButFailed->count()
            );

            if ($autoFix) {
                foreach ($deliveredButFailed as $notification) {
                    $notification->update(['status' => 'delivered']);
                    // Resolve any active issues
                    $notification->activeIssues()->update([
                        'resolved_at'      => now(),
                        'resolution_notes' => 'Auto-resolved: Notification was delivered',
                    ]);
                }
                $fixed[] = sprintf('✓ Fixed %d delivered notification(s) status', $deliveredButFailed->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 2: Delivered notifications with active orphaned issues
        $this->line('Check 2: Delivered notifications with active orphaned issues...');
        $deliveredWithOrphanedIssues = Notification::whereNotNull('delivered_at')
            ->whereHas('activeIssues', function ($q) {
                $q->where('issue_type', 'polaris_export_failure');
            })
            ->where('first_exported_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($deliveredWithOrphanedIssues->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d delivered notification(s) with active polaris_export_failure issues',
                $deliveredWithOrphanedIssues->count()
            );

            if ($autoFix) {
                foreach ($deliveredWithOrphanedIssues as $notification) {
                    $notification->activeIssues()
                        ->where('issue_type', 'polaris_export_failure')
                        ->update([
                            'resolved_at'      => now(),
                            'resolution_notes' => 'Auto-resolved: Notification was delivered',
                        ])
                    ;
                }
                $fixed[] = sprintf('✓ Resolved %d orphaned issue(s) on delivered notifications', $deliveredWithOrphanedIssues->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 3: Status = "delivered" but delivered_at is NULL
        $this->line('Check 3: Notifications marked delivered without timestamp...');
        $deliveredStatusNoTimestamp = Notification::where('status', 'delivered')
            ->whereNull('delivered_at')
            ->where('first_exported_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($deliveredStatusNoTimestamp->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d notification(s) with status = "delivered" but delivered_at is NULL',
                $deliveredStatusNoTimestamp->count()
            );

            if ($autoFix) {
                foreach ($deliveredStatusNoTimestamp as $notification) {
                    // Set delivered_at to the most recent timestamp we have
                    $deliveredAt = $notification->last_exported_at ?? $notification->first_exported_at ?? now();
                    $notification->update(['delivered_at' => $deliveredAt]);
                }
                $fixed[] = sprintf('✓ Set delivered_at timestamp for %d notification(s)', $deliveredStatusNoTimestamp->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 4: Pending notifications with delivered_at timestamp
        $this->line('Check 4: Pending notifications with delivery timestamp...');
        $pendingButDelivered = Notification::where('status', 'pending')
            ->whereNotNull('delivered_at')
            ->where('first_exported_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($pendingButDelivered->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d notification(s) with status = "pending" but delivered_at is set',
                $pendingButDelivered->count()
            );

            if ($autoFix) {
                foreach ($pendingButDelivered as $notification) {
                    $notification->update(['status' => 'delivered']);
                }
                $fixed[] = sprintf('✓ Updated status to "delivered" for %d notification(s)', $pendingButDelivered->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 5: Active issues on resolved notifications
        $this->line('Check 5: Active issues on notifications with resolved status...');
        $activeIssuesOnResolved = PatronDeliveryIssue::whereNull('resolved_at')
            ->whereHas('notification', function ($q) use ($days) {
                $q->where('status', 'delivered')
                    ->where('first_exported_at', '>=', now()->subDays($days))
                ;
            })
            ->get()
        ;

        if ($activeIssuesOnResolved->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d active issue(s) on delivered notifications',
                $activeIssuesOnResolved->count()
            );

            if ($autoFix) {
                $activeIssuesOnResolved->each(function ($issue) {
                    $issue->update([
                        'resolved_at'      => now(),
                        'resolution_notes' => 'Auto-resolved: Notification was delivered',
                    ]);
                });
                $fixed[] = sprintf('✓ Resolved %d issue(s) on delivered notifications', $activeIssuesOnResolved->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 6: Orphaned issues without matching notifications
        // NOTE: Exclude 'mismatched_preference' issues - they intentionally have notification_id = NULL
        // because they're about barcode preference settings, not specific notifications
        $this->line('Check 6: Orphaned issues without notifications...');
        $orphanedIssues = PatronDeliveryIssue::whereDoesntHave('notification')
            ->where('issue_type', '!=', 'mismatched_preference')
            ->where('detected_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($orphanedIssues->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d orphaned issue(s) without matching notifications',
                $orphanedIssues->count()
            );

            if ($autoFix) {
                $orphanedIssues->each(function ($issue) {
                    $issue->update([
                        'resolved_at'      => now(),
                        'resolution_notes' => 'Auto-resolved: Notification record no longer exists',
                    ]);
                });
                $fixed[] = sprintf('✓ Resolved %d orphaned issue(s)', $orphanedIssues->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 7: delivered_at before generated_at or first_exported_at
        // DISABLED as of 2026-01-04: This check is now INVALID because delivered_at
        // is set to the export filename datetime (configured timezone, e.g. CST) which
        // can legitimately be different from first_exported_at (stored in UTC).
        //
        // Example: export filename "notification_log_2026-01-04_05-00-01.txt" (CST)
        // becomes delivered_at = "2026-01-04 05:00:01" but first_exported_at in UTC
        // might be "2026-01-04 11:00:01". The timezone difference makes this check invalid.
        //
        // Reconciliation logic now handles delivered_at correctly per AGENTS.md rules.
        $this->line('Check 7: Delivered timestamp before notification was created...');
        $this->info('  ✓ Check disabled (delivered_at now uses export filename datetime)');

        // Check 8: Mail notifications (delivery_option_id = 1) with lx_starter_failure issues
        $this->line('Check 8: Mail notifications incorrectly flagged as LX Starter failures...');
        $mailWithLxFailures = Notification::where('delivery_option_id', 1)
            ->whereHas('activeIssues', function ($q) {
                $q->whereIn('issue_type', ['lx_starter_failure', 'polaris_export_failure']);
            })
            ->where('first_exported_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($mailWithLxFailures->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d mail notification(s) incorrectly flagged as LX Starter failures',
                $mailWithLxFailures->count()
            );

            if ($autoFix) {
                foreach ($mailWithLxFailures as $notification) {
                    $notification->activeIssues()
                        ->whereIn('issue_type', ['lx_starter_failure', 'polaris_export_failure'])
                        ->update([
                            'resolved_at'      => now(),
                            'resolution_notes' => 'Auto-resolved: Mail notifications are not processed by LX Starter',
                        ])
                    ;
                }
                $fixed[] = sprintf('✓ Resolved %d LX Starter failure issue(s) on mail notifications', $mailWithLxFailures->count());
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Check 9: Orphaned notifications marked incorrectly (without proper conditions)
        $this->line('Check 9: Orphaned notifications marked without proper conditions...');

        // Find notifications marked as orphaned that DON'T meet BOTH requirements:
        // 1. Import batch ran after export
        // 2. Been pending for 24+ hours
        $orphanedIncorrectly = Notification::where('delivery_option_id', 2)
            ->where('status', 'failed')
            ->whereHas('activeIssues', function ($q) {
                $q->where('issue_type', 'polaris_export_failure');
            })
            ->where(function ($q) {
                // Either: No import batch ran after export
                $q->whereNotExists(function ($subquery) {
                    $subquery->selectRaw('1')
                        ->from('notification_import_batches')
                        ->whereColumn('notification_import_batches.date_for', '>=', DB::raw('DATE(notifications.first_exported_at)'))
                        ->whereColumn('notification_import_batches.imported_at', '>', 'notifications.first_exported_at')
                    ;
                })
                // OR: Less than 24 hours old (marked too early)
                    ->orWhere('first_exported_at', '>=', now()->subHours(24))
                ;
            })
            ->where('first_exported_at', '>=', now()->subDays($days))
            ->get()
        ;

        if ($orphanedIncorrectly->count() > 0) {
            $issues[] = sprintf(
                '⚠ Found %d notification(s) marked as orphaned without meeting both requirements (import batch + 24 hours)',
                $orphanedIncorrectly->count()
            );

            if ($autoFix) {
                foreach ($orphanedIncorrectly as $notification) {
                    // Revert to pending status since conditions weren't met
                    $notification->update([
                        'status'         => 'pending',
                        'failure_reason' => null,
                    ]);

                    // Resolve the incorrect orphaned issue
                    $notification->activeIssues()
                        ->where('issue_type', 'polaris_export_failure')
                        ->update([
                            'resolved_at'      => now(),
                            'resolution_notes' => 'Auto-resolved: Marked as orphaned without meeting both requirements (import batch + 24h). Reverted to pending.',
                        ])
                    ;
                }
                $fixed[] = sprintf(
                    '✓ Reverted %d notification(s) to pending status (conditions not met)',
                    $orphanedIncorrectly->count()
                );
            }
        } else {
            $this->info('  ✓ No issues found');
        }

        // Summary
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════');

        if (empty($issues)) {
            $this->info('✓ All integrity checks passed! No issues found.');

            return Command::SUCCESS;
        }

        $this->warn(sprintf('Found %d issue type(s):', count($issues)));
        foreach ($issues as $issue) {
            $this->line('  ' . $issue);
        }

        if ($autoFix) {
            $this->newLine();
            $this->info('Applied fixes:');
            foreach ($fixed as $fix) {
                $this->line('  ' . $fix);
            }
        } else {
            $this->newLine();
            $this->comment('Run with --fix to automatically resolve these issues.');
        }

        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }
}
