<?php

namespace Dcplibrary\Notices\Support\QueryScopes;

use Illuminate\Database\Eloquent\Builder;

/**
 * Reusable query scope for orphaned notification logic.
 *
 * DEPRECATED FOR EMAIL/MAIL: As of 2026-01-04, Email/Mail notifications are now handled
 * entirely by the reconciliation logic in NotificationReconciler. They use NotificationLog
 * + Summary Reports to determine status per AGENTS.md sacred rules.
 *
 * This scope is now DISABLED for Email (2) and Mail (1) delivery options.
 * It may still apply to Voice/SMS in the future if needed.
 *
 * Orphaned notifications WERE emails exported from Polaris but never sent by LX Starter.
 * They represented a communication failure between Polaris and the LX Starter service,
 * where notifications were successfully exported from the ILS but never picked up or
 * processed by the notification delivery system.
 *
 * ## Identification Criteria (OBSOLETE FOR EMAIL/MAIL)
 *
 * Orphaned notifications were identified by being email delivery (delivery_option_id = 2)
 * and meeting ONE of the following conditions:
 *
 * 1. **Already marked as failed**: Status = 'failed' with an active polaris_export_failure issue
 * 2. **Stuck pending**: Status = 'pending' for >24 hours since first_exported_at
 *
 * ## Purpose
 *
 * This class centralizes the complex orphaned notification query logic to prevent duplication
 * across multiple service methods and controllers. Before this refactoring, the logic was
 * duplicated in:
 * - NotificationService::searchNotifications() (for filtering)
 * - NotificationService::getOrphanedNotifications() (for dashboard display)
 * - NotificationService::processOrphanedNotifications() (for marking as failed)
 *
 * ## Usage Example
 *
 * ```php
 * // Get all orphaned notifications from the last 7 days
 * $orphaned = Notification::query();
 * OrphanedNotificationScope::apply($orphaned)
 *     ->where('first_exported_at', '>=', now()->subDays(7))
 *     ->get();
 *
 * // Get orphaned notifications ready for processing (pending only)
 * $toProcess = Notification::query();
 * OrphanedNotificationScope::applyForProcessing($toProcess)
 *     ->where('first_exported_at', '>=', now()->subDays(7))
 *     ->get();
 * ```
 *
 * @author DCPL Development Team
 *
 * @since 1.0.0
 * @see \Dcplibrary\Notices\Services\NotificationService::getOrphanedNotifications()
 * @see \Dcplibrary\Notices\Services\NotificationService::processOrphanedNotifications()
 */
class OrphanedNotificationScope
{
    /**
     * Apply orphaned notification filters to a query.
     *
     * This method adds WHERE clauses to identify orphaned notifications, including
     * both those already marked as failed AND those still pending >24 hours.
     *
     * The query will return notifications where:
     * - delivery_option_id = 2 (Email only)
     * - AND (status = 'failed' with polaris_export_failure issue
     *        OR status = 'pending' and first_exported_at < cutoff date)
     *
     * @param Builder             $query      The Eloquent query builder for Notification model
     * @param null|\Carbon\Carbon $cutoffDate Optional cutoff date for pending notifications (default: 24 hours ago from now)
     *
     * @return Builder Modified query builder with orphaned filters applied
     *
     * @example
     * ```php
     * $query = Notification::query();
     * OrphanedNotificationScope::apply($query);
     * $orphaned = $query->get(); // All orphaned notifications
     * ```
     * @example
     * ```php
     * // Use custom cutoff (48 hours instead of 24)
     * $query = Notification::query();
     * OrphanedNotificationScope::apply($query, now()->subHours(48));
     * ```
     */
    public static function apply(Builder $query, $cutoffDate = null): Builder
    {
        // DISABLED FOR EMAIL/MAIL as of 2026-01-04
        // Email/Mail notifications are now handled entirely by reconciliation logic
        // per AGENTS.md sacred rules (NotificationLog + Summary Reports)
        //
        // Return query that matches nothing
        return $query->whereRaw('1 = 0');
    }

    /**
     * Apply orphaned notification filters for processing (pending only).
     *
     * CRITICAL: This method ONLY marks notifications as orphaned if an import batch has been
     * executed AFTER the notification was exported. This prevents false positives in dev
     * environments where imports are run manually and infrequently.
     *
     * The query will return notifications where:
     * - delivery_option_id = 2 (Email only)
     * - AND status = 'pending'
     * - AND an import batch exists with date_for >= notification's export date
     * - AND that import batch was created AFTER the notification was exported
     *
     * This ensures notifications are only marked as failed if they're STILL pending
     * AFTER reconciliation has had a chance to update them.
     *
     * After processing, these notifications will:
     * 1. Have status changed to 'failed'
     * 2. Get a failure_reason explaining they were orphaned
     * 3. Have a PatronDeliveryIssue created with type 'polaris_export_failure'
     *
     * @param Builder             $query      The Eloquent query builder for Notification model
     * @param null|\Carbon\Carbon $cutoffDate DEPRECATED - kept for backward compatibility but ignored
     *
     * @return Builder Modified query builder with processing filters applied
     *
     * @example
     * ```php
     * // Get pending orphaned notifications to mark as failed
     * $query = Notification::query();
     * OrphanedNotificationScope::applyForProcessing($query)
     *     ->where('first_exported_at', '>=', now()->subDays(7))
     *     ->get();
     * ```
     *
     * @see \Dcplibrary\Notices\Console\ProcessOrphanedNotificationsCommand
     */
    public static function applyForProcessing(Builder $query, $cutoffDate = null): Builder
    {
        // DISABLED FOR EMAIL/MAIL as of 2026-01-04
        // Email/Mail notifications are now handled entirely by reconciliation logic
        // per AGENTS.md sacred rules (NotificationLog + Summary Reports)
        //
        // Orphaned notification detection is OBSOLETE for Email/Mail.
        // All failure cases are now handled by NotificationReconciler:
        // - NotificationLog + Summary Report both failure → failed
        // - NotificationLog success but NOT in Summary Report → LX Starter communication error
        // - notification_status_id NULL after 48 hours → Polaris error
        //
        // Return query that matches nothing
        return $query->whereRaw('1 = 0');
    }
}
