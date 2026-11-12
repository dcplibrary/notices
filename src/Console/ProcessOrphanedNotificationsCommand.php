<?php

namespace Dcplibrary\Notices\Console;

use Dcplibrary\Notices\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Artisan command to process orphaned notifications.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * CRITICAL: DO NOT mark notifications as failed based on time elapsed!
 * ONLY mark as failed if STILL pending AFTER import/reconciliation has run!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * Orphaned notifications are email notifications that were:
 * - Exported from Polaris (first_exported_at is set)
 * - Still pending status AFTER an import batch has been executed
 * - Never successfully sent by LX Starter
 *
 * ## How Orphaned Detection Works
 *
 * The system uses IMPORT-BATCH-BASED detection, NOT time-based detection:
 *
 * 1. Notification is exported from Polaris (first_exported_at is set)
 * 2. Notification enters "pending" status
 * 3. Import/reconciliation runs (creates notification_import_batch record)
 * 4. Reconciler attempts to match notification with delivery confirmations
 * 5. IF notification is STILL pending AFTER reconciliation: mark as orphaned
 *
 * This ensures:
 * - Dev environments: No false positives when imports run manually/infrequently
 * - Production: Notifications marked as failed only after next scheduled import
 * - Accurate tracking: Only truly orphaned notifications are flagged
 *
 * ## Execution Order (CRITICAL)
 *
 * This command MUST be called AFTER the import/reconciliation pipeline:
 *
 * 1. Import FTP exports (notifications from Polaris)
 * 2. Import email confirmations (delivery status from LX Starter)
 * 3. Run reconciliation (match confirmations to notifications)
 * 4. **THEN** run this command to mark still-pending as orphaned
 *
 * If you run this command BEFORE reconciliation, you will get false positives!
 *
 * ## Usage
 *
 * ```bash
 * # Part of automated import pipeline (correct usage)
 * php artisan notices:import  # This calls process-orphaned at the end
 *
 * # Manual execution (ensure reconciliation ran first!)
 * php artisan notices:process-orphaned --days=7
 * ```
 *
 * @see \Dcplibrary\Notices\Support\QueryScopes\OrphanedNotificationScope::applyForProcessing()
 * @see ImportCommand (line 128 - calls this command)
 */
class ProcessOrphanedNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notices:process-orphaned
                            {--days=7 : Number of days back to check for orphaned notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark orphaned notifications as failed and create delivery issues (Polaris → LX Starter communication failures). Should be run AFTER notices:import.';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $service): int
    {
        $days = (int) $this->option('days');

        $this->info("Processing orphaned notifications from the last {$days} days...");

        $results = $service->processOrphanedNotifications($days);

        $this->newLine();
        $this->info("✓ Processed {$results['processed']} orphaned notification(s)");
        $this->info("✓ Created {$results['issues_created']} new delivery issue(s)");
        $this->info("✓ Total orphaned: {$results['total_orphaned']}");

        if ($results['processed'] > 0) {
            $this->newLine();
            $this->line("These notifications have been marked as 'failed' with delivery issues created.");
            $this->line('View them at: /notices/issues/orphaned');
        }

        return Command::SUCCESS;
    }
}
