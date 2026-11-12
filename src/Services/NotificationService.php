<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Support\Collection;

/**
 * Service for notification-related business logic and queries.
 *
 * This service encapsulates complex notification queries and calculations,
 * keeping controllers thin and logic reusable. Provides methods for:
 * - Statistical aggregation (daily and date range)
 * - Delivery confirmation tracking
 * - Patron notification history
 * - Active issue management
 * - Failed delivery string tracking
 * - Bulk issue resolution
 *
 * All date parameters should be in Y-m-d format (e.g., '2024-12-24').
 * Most queries use date comparison on the first_exported_at column.
 *
 * @author DCPL Development Team
 */
class NotificationService
{
    protected ConfirmationQueryService $confirmationQueryService;
    protected NotificationMetricsQueryService $notificationMetricsQueryService;
    protected NotificationDiagnosticsQueryService $notificationDiagnosticsQueryService;
    protected NotificationQueryService $notificationQueryService;
    protected IssueQueryService $issueQueryService;
    protected FailedContactQueryService $failedContactQueryService;
    protected IssueResolutionService $issueResolutionService;
    protected OrphanedNotificationQueryService $orphanedNotificationQueryService;
    protected ImportStatusService $importStatusService;
    protected FailedContactResponseBuilder $failedContactResponseBuilder;

    public function __construct(
        ?ConfirmationQueryService $confirmationQueryService = null,
        ?NotificationMetricsQueryService $notificationMetricsQueryService = null,
        ?NotificationDiagnosticsQueryService $notificationDiagnosticsQueryService = null,
        ?NotificationQueryService $notificationQueryService = null,
        ?IssueQueryService $issueQueryService = null,
        ?FailedContactQueryService $failedContactQueryService = null,
        ?IssueResolutionService $issueResolutionService = null,
        ?OrphanedNotificationQueryService $orphanedNotificationQueryService = null,
        ?ImportStatusService $importStatusService = null,
        ?FailedContactResponseBuilder $failedContactResponseBuilder = null
    ) {
        // Keep manual instantiation paths (e.g., docs/examples) working.
        $this->confirmationQueryService            = $confirmationQueryService            ?? new ConfirmationQueryService();
        $this->notificationMetricsQueryService     = $notificationMetricsQueryService     ?? new NotificationMetricsQueryService();
        $this->notificationDiagnosticsQueryService = $notificationDiagnosticsQueryService ?? new NotificationDiagnosticsQueryService();
        $this->notificationQueryService            = $notificationQueryService            ?? new NotificationQueryService();
        $this->issueQueryService                   = $issueQueryService                   ?? new IssueQueryService();
        $this->failedContactQueryService           = $failedContactQueryService           ?? new FailedContactQueryService();
        $this->issueResolutionService              = $issueResolutionService              ?? new IssueResolutionService();
        $this->orphanedNotificationQueryService    = $orphanedNotificationQueryService    ?? new OrphanedNotificationQueryService();
        $this->importStatusService                 = $importStatusService                 ?? new ImportStatusService();
        $this->failedContactResponseBuilder        = $failedContactResponseBuilder        ?? new FailedContactResponseBuilder();
    }

    /**
     * Get clustered NotificationLog email rows where delivery_string is empty/NULL.
     *
     * This is intended as a diagnostic view for Polaris/LX Starter system/data failures
     * (e.g., clustered blank delivery strings at the same minute).
     *
     * @param string $dateFrom Y-m-d (inclusive)
     * @param string $dateTo   Y-m-d (inclusive)
     *
     * @return Collection Collection of objects:
     *                    - minute_bucket (string, Y-m-d H:i)
     *                    - count (int)
     *                    - first_at (string|null)
     *                    - last_at (string|null)
     */
    public function getEmailBlankDeliveryStringClusters(string $dateFrom, string $dateTo): Collection
    {
        return $this->confirmationQueryService->getEmailBlankDeliveryStringClusters($dateFrom, $dateTo);
    }

    /**
     * Get daily notification statistics broken down by type and delivery method.
     *
     * Wrapper for getDateRangeStatistics() for single-day queries.
     * Maintains backward compatibility with existing code.
     *
     * @param string $date Date in Y-m-d format (e.g., '2024-12-24')
     *
     * @return array Statistics array with keys: totals, by_type, by_method, issues
     *
     * @see self::getDateRangeStatistics()
     */
    public function getDailyStatistics(string $date): array
    {
        return $this->notificationMetricsQueryService->getDailyStatistics($date);
    }

    /**
     * Get date range notification statistics broken down by type and delivery method.
     *
     * Returns comprehensive statistics for notifications exported within the date range:
     *
     * Structure:
     * [
     *   'totals' => object {
     *     total: int,           // Total notifications
     *     delivered: int,       // Successfully delivered count
     *     failed: int,          // Failed delivery count
     *     pending: int,         // Pending/unknown status count
     *     success_rate: float   // Percentage delivered (0-100)
     *   },
     *   'by_type' => Collection [
     *     'Hold Notice' => object { total, delivered, failed, pending, success_rate },
     *     'Overdue Notice' => object { ... },
     *     ...
     *   ],
     *   'by_method' => Collection [
     *     'E-mail' => object { total, delivered, failed, pending },
     *     'Voice Notification' => object { ... },
     *     ...
     *   ],
     *   'issues' => Collection [
     *     'failed_delivery_string' => int,  // Count of this issue type
     *     'manual_review' => int,
     *     ...
     *   ]
     * ]
     *
     * @param string $dateFrom Start date in Y-m-d format
     * @param string $dateTo   End date in Y-m-d format (inclusive)
     *
     * @return array Statistics with totals, by_type, by_method, and issues
     */
    public function getDateRangeStatistics(string $dateFrom, string $dateTo): array
    {
        return $this->notificationMetricsQueryService->getDateRangeStatistics($dateFrom, $dateTo);
    }

    /**
     * Get failed notification counts grouped by delivery method (delivery_option_id).
     *
     * If $dateFrom/$dateTo are omitted, this returns all-time counts.
     *
     * Labels MUST use override-aware display labels (lookup_table_overrides).
     *
     * @return Collection<int, object{delivery_option_id:null|int,label:string,failed:int}>
     */
    public function getFailedNotificationCountsByDeliveryMethod(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return $this->notificationMetricsQueryService->getFailedNotificationCountsByDeliveryMethod($dateFrom, $dateTo);
    }

    /**
     * Get failed notification counts grouped by notification type.
     *
     * If $dateFrom/$dateTo are omitted, this returns all-time counts.
     *
     * Labels MUST use override-aware display labels (lookup_table_overrides).
     *
     * @return Collection<int, object{notification_type_id:null|int,notification_type:string,failed:int}>
     */
    public function getFailedNotificationCountsByNotificationType(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return $this->notificationMetricsQueryService->getFailedNotificationCountsByNotificationType($dateFrom, $dateTo);
    }

    /**
     * Count Email failures attributed to the Polaris → LX Starter pipeline.
     *
     * This includes:
     * - Notifications with a polaris_export_failure issue (active or resolved)
     * - Notifications failed with an LX Starter communication failure reason
     * - Notifications failed due to blank NotificationLog delivery_string (system/data failure)
     *
     * If $dateFrom/$dateTo are omitted, this returns all-time count.
     */
    public function countLxStarterFailures(?string $dateFrom = null, ?string $dateTo = null): int
    {
        return $this->notificationMetricsQueryService->countLxStarterFailures($dateFrom, $dateTo);
    }

    /**
     * Get notification history for a specific patron.
     *
     * @param string $barcode Patron barcode
     *
     * @return Collection
     */
    public function getPatronNotificationHistory(string $barcode, int $perPage = 50)
    {
        return $this->notificationDiagnosticsQueryService->getPatronNotificationHistory($barcode, $perPage);
    }

    /**
     * Get all active delivery issues with related data.
     *
     * @param null|string $issueType Filter by specific issue type
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getActiveIssues(?string $issueType = null)
    {
        return $this->issueQueryService->getActiveIssues($issueType);
    }

    /**
     * Search and filter delivery issues with pagination and sorting.
     *
     * Returns paginated delivery issues with their related notification and delivery option data.
     * By default, only shows unresolved issues unless 'show_resolved' filter is provided.
     *
     * Supported filters:
     * - search: Combined search across patron barcode, patron names, title, and item barcode
     * - date_from: Filter issues detected on or after this date
     * - date_to: Filter issues detected on or before this date
     * - delivery_option_id: Filter by specific delivery method (1=Mail, 2=Email, 3=Voice, 8=SMS)
     * - show_resolved: When set, includes resolved issues (default: only unresolved)
     *
     * @param null|string $issueType      Optional issue type filter (failed_delivery_string, lx_starter_failure, null_preference, manual_review, system_error)
     * @param array       $filters        Associative array of filter criteria
     * @param string      $sortBy         Column to sort by (detected_at, patron_barcode, issue_type)
     * @param string      $sortDir        Sort direction ('asc' or 'desc')
     * @param bool        $includeDetails When true, eager-load related notification/failed-string models
     * @param int         $perPage        Page size (bounded to 1..200)
     * @param bool        $includeTotals  When false, use simple pagination to avoid COUNT(*)
     *
     * @return PaginatorContract Paginated collection of PatronDeliveryIssue models
     */
    public function searchIssues(
        ?string $issueType = null,
        array $filters = [],
        string $sortBy = 'notification_date',
        string $sortDir = 'desc',
        bool $includeDetails = true,
        int $perPage = 50,
        bool $includeTotals = true
    ) {
        return $this->issueQueryService->searchIssues(
            $issueType,
            $filters,
            $sortBy,
            $sortDir,
            $includeDetails,
            $perPage,
            $includeTotals
        );
    }

    /**
     * Get statistics for active (unresolved) delivery issues.
     *
     * Provides comprehensive metrics for the issues dashboard. All counts and
     * statistics focus on ACTIVE (unresolved) issues only, except for resolution
     * metrics which naturally require resolved issues.
     *
     * Returned statistics:
     * - total_active: Count of unresolved issues
     * - total_resolved: Count of all resolved issues (all time)
     * - detected_today: Count of unresolved issues detected today
     * - resolved_today: Count of issues resolved today
     * - avg_resolution_hours: Average time to resolve issues (last 30 days), rounded to 1 decimal
     * - by_type: Breakdown of active issues by type (failed_delivery_string, manual_review, system_error)
     *
     * @return array Associative array of statistics for dashboard display
     */
    public function getIssueStatistics(): array
    {
        return $this->notificationMetricsQueryService->getIssueStatistics();
    }

    /**
     * Get a small sample of unresolved failed contacts for dashboard CTA cards.
     *
     * Results are derived from failed_delivery_strings and enriched with a
     * representative patron (when available) so the dashboard can say
     * "These patrons are not getting notifications" with concrete examples.
     *
     * For SMS/Voice contacts this method will:
     * - Look up the phone in polaris_hold/renew/overdue notifications.
     * - Fall back to Notification rows whose failure_reason/raw_payload
     *   mention the phone when raw exports are outside the current window.
     *
     * @param array<int> $deliveryOptionIds Filter by delivery option IDs (e.g., [3, 8] for SMS/Voice, [2] for Email)
     * @param int        $limit             Maximum number of contacts to return
     *
     * @return Collection<FailedDeliveryString>
     */
    public function getDashboardFailedContacts(array $deliveryOptionIds = [], int $limit = 5): Collection
    {
        return $this->failedContactQueryService->getDashboardFailedContacts($deliveryOptionIds, $limit);
    }

    /**
     * Get aggregate counts of unresolved failed contacts by channel.
     *
     * Counts include:
     * - FailedDeliveryString rows (unresolved)
     * - mismatched_preference PatronDeliveryIssue rows (unresolved)
     *
     * Returns counts grouped into logical buckets used by the dashboard/UI:
     * - sms_voice: Voice (3) + SMS (8) + mismatched_preference
     * - email: Email (2)
     * - combined: sms_voice + email
     *
     * @return array{
     *   sms_voice: array{patrons:int, contacts:int, failures:int},
     *   email: array{patrons:int, contacts:int, failures:int},
     *   combined: array{patrons:int, contacts:int, failures:int}
     * }
     */
    public function getFailedContactCountsByChannel(): array
    {
        return $this->failedContactQueryService->getFailedContactCountsByChannel();
    }

    /**
     * Date-scoped variant of getFailedContactCountsByChannel().
     *
     * When a valid $dateFrom/$dateTo range is provided, limits counts to
     * unresolved failed contacts whose most recent failure (FailedDeliveryString
     * last_failed_at) or detection time (PatronDeliveryIssue detected_at) falls
     * within the range. When the range is missing/invalid, falls back to the
     * all-time unresolved aggregates.
     */
    public function getFailedContactCountsByChannelForRange(?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->failedContactQueryService->getFailedContactCountsByChannelForRange($dateFrom, $dateTo);
    }

    /**
     * Get failed delivery strings (emails/phones that have failed).
     *
     * By default, only unresolved rows (is_resolved = false) are returned so that
     * active problems are highlighted. When $includeResolved is true, both active
     * and resolved contacts are returned for history views.
     *
     * This powers the Failed Contacts summary view and is the canonical
     * source for `FailedDeliveryString` aggregates.
     *
     * Also includes mismatched preference issues (Voice/SMS only) as they represent
     * failed contacts that need to be updated.
     */
    public function getFailedDeliveryStrings(bool $includeResolved = false, array $filters = []): Collection
    {
        return $this->failedContactQueryService->getFailedDeliveryStrings($includeResolved, $filters);
    }

    /**
     * Get paginated failed delivery strings for API list endpoints.
     *
     * This intentionally avoids expensive enrichment and cross-table synthesis so
     * large unresolved datasets can be paged directly in SQL.
     */
    public function getFailedDeliveryStringsPaginatedForApi(
        bool $includeResolved = false,
        array $filters = [],
        int $perPage = 50,
        int $page = 1,
        bool $includeTotals = false
    ): PaginatorContract {
        return $this->failedContactQueryService->getFailedDeliveryStringsPaginatedForApi(
            $includeResolved,
            $filters,
            $perPage,
            $page,
            $includeTotals
        );
    }

    /**
     * Build failed contact JSON response structure for API.
     *
     * Constructs a standardized JSON response array for failed contact details
     * used by the Failed Contact edit/resolve modal. Handles both regular
     * FailedDeliveryString records and mismatched preference issues.
     *
     * @param array $data Array containing:
     *                    - id (string|int): Contact ID
     *                    - delivery_string (string): Phone or email
     *                    - string_type (string): 'phone' or 'email'
     *                    - delivery_option_id (int): Delivery option ID
     *                    - delivery_option_label (string): Display label
     *                    - failure_source (string): Source of failure
     *                    - failure_details (string): Details text
     *                    - failure_count (int): Number of failures
     *                    - affected_patron_count (int): Number of affected patrons
     *                    - first_failed_at (Carbon|null): First failure date
     *                    - last_failed_at (Carbon|null): Last failure date
     *                    - is_resolved (bool): Whether resolved
     *                    - resolved_at (Carbon|null): Resolution date
     *                    - resolution_notes (string|null): Resolution notes
     *                    - patron (object|null): Patron object with patron_id, patron_barcode, patron_first_name, patron_last_name
     *
     * @return array JSON response array structure
     */
    public function buildFailedContactApiResponse(array $data): array
    {
        return $this->failedContactResponseBuilder->buildFailedContactApiResponse($data);
    }

    /**
     * Get a single failed delivery string with enriched patron information.
     *
     * Unlike getFailedDeliveryStrings(), this will return a record even if it
     * has already been resolved so that the modal can show historical details.
     */
    public function getFailedDeliveryString(int $id): FailedDeliveryString
    {
        return $this->failedContactQueryService->getFailedDeliveryString($id);
    }

    /**
     * Find patron information by patron_id or patron_barcode.
     *
     * Searches across polaris_hold_notifications, polaris_renewal_notifications,
     * and polaris_overdue_notifications tables. First tries patron_id if provided,
     * then falls back to patron_barcode if no match found.
     *
     * @param null|int    $patronId Patron ID to search for
     * @param null|string $barcode  Patron barcode to search for
     *
     * @return null|object Patron object with patron_id, patron_barcode, patron_first_name, patron_last_name, or null if not found
     */
    public function findPatronByIdOrBarcode(?int $patronId, ?string $barcode): ?object
    {
        return $this->failedContactQueryService->findPatronByIdOrBarcode($patronId, $barcode);
    }

    /**
     * Get delivery confirmation for a mismatched preference issue.
     *
     * Retrieves the delivery confirmation record that was used to create
     * a mismatched preference issue. Returns the phone number and delivery
     * option ID from the confirmation.
     *
     * @param string $patronBarcode Patron barcode to look up
     *
     * @return null|object Object with 'delivery_string' (phone) and 'delivery_option_id', or null if not found
     */
    public function getMismatchedPreferenceConfirmation(string $patronBarcode): ?object
    {
        return $this->failedContactQueryService->getMismatchedPreferenceConfirmation($patronBarcode);
    }

    /**
     * Get patrons affected by a specific failed delivery string.
     */
    public function getAffectedPatrons(int $failedStringId): Collection
    {
        return $this->failedContactQueryService->getAffectedPatrons($failedStringId);
    }

    /**
     * Resolve a single delivery issue and attach a resolution note.
     */
    public function resolveIssue(PatronDeliveryIssue $issue, string $resolutionNotes): bool
    {
        return $this->issueResolutionService->resolveIssue($issue, $resolutionNotes);
    }

    /**
     * Resolve an aggregated failed delivery string (FailedDeliveryString).
     *
     * Marks the failed contact as resolved, optionally resolves any linked
     * PatronDeliveryIssue rows, and records a Note for audit history.
     */
    public function resolveFailedDeliveryString(
        FailedDeliveryString $failedString,
        ?string $patronBarcode,
        ?string $patronFirstName,
        ?string $patronLastName,
        ?string $resolutionType,
        string $resolutionNotes
    ): bool {
        return $this->issueResolutionService->resolveFailedDeliveryString(
            $failedString,
            $patronBarcode,
            $patronFirstName,
            $patronLastName,
            $resolutionType,
            $resolutionNotes
        );
    }

    /**
     * Bulk resolve issues by notification IDs.
     *
     * Creates a resolution note for each affected issue so that
     * there is an audit trail even when changes are made in bulk.
     *
     * @return int Number of issues resolved
     */
    public function bulkResolveByNotifications(array $notificationIds, string $resolutionNotes): int
    {
        return $this->issueResolutionService->bulkResolveByNotifications($notificationIds, $resolutionNotes);
    }

    /**
     * Bulk resolve issues by time window.
     *
     * @param string $startTime Time in H:i format (e.g., "08:03")
     * @param string $endTime   Time in H:i format (e.g., "08:06")
     * @param string $date      Date in Y-m-d format
     *
     * @return int Number of issues resolved
     */
    public function bulkResolveByTimeWindow(string $startTime, string $endTime, string $date, string $resolutionNotes): int
    {
        return $this->issueResolutionService->bulkResolveByTimeWindow(
            $startTime,
            $endTime,
            $date,
            $resolutionNotes
        );
    }

    /**
     * Bulk resolve issues for a specific patron.
     *
     * @return int Number of issues resolved
     */
    public function bulkResolveByPatron(string $patronBarcode, string $resolutionNotes): int
    {
        return $this->issueResolutionService->bulkResolveByPatron($patronBarcode, $resolutionNotes);
    }

    /**
     * Get delivery confirmations for a specific date.
     *
     * @param string $date Date in Y-m-d format
     *
     * @return array Grouped by confirmation source
     */
    public function getDeliveryConfirmations(string $date): array
    {
        return $this->confirmationQueryService->getDeliveryConfirmations($date);
    }

    /**
     * Get delivery confirmations for a date range.
     *
     * @param string $dateFrom Start date in Y-m-d format
     * @param string $dateTo   End date in Y-m-d format
     *
     * @return array Grouped by confirmation source
     */
    public function getDateRangeConfirmations(string $dateFrom, string $dateTo): array
    {
        return $this->confirmationQueryService->getDateRangeConfirmations($dateFrom, $dateTo);
    }

    /**
     * Search notifications with filters and sorting.
     *
     * @param array  $filters       Filters: date_from, date_to, status, notification_type,
     *                              delivery_option_id, patron_barcode, patron_name, title, item_barcode
     * @param string $sortBy        Column to sort by (defaults to first_exported_at)
     * @param string $sortDir       Sort direction: asc or desc (defaults to desc)
     * @param int    $perPage       Page size (bounded to 1..100)
     * @param bool   $includeTotals When false, uses simple pagination to avoid COUNT(*)
     *
     * @return PaginatorContract
     */
    public function searchNotifications(
        array $filters = [],
        string $sortBy = 'first_exported_at',
        string $sortDir = 'desc',
        int $perPage = 50,
        bool $includeTotals = true
    ) {
        return $this->notificationQueryService->searchNotifications(
            $filters,
            $sortBy,
            $sortDir,
            $perPage,
            $includeTotals
        );
    }

    /**
     * Get orphaned notification analysis for a given lookback period.
     *
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * CRITICAL: This method retrieves notifications ALREADY marked as failed!
     * It does NOT calculate orphaned status dynamically. Status is determined
     * at import time by notices:process-orphaned.
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     *
     * Orphaned notifications are email notifications that were exported from Polaris
     * but never sent by LX Starter (Polaris → LX Starter communication failures).
     *
     * How notifications become "orphaned" (at import time):
     * 1. Notification exported from Polaris (first_exported_at set)
     * 2. Remains in 'pending' status for 24+ hours
     * 3. THE FIRST IMPORT AFTER 24 HOURS runs reconciliation
     * 4. If STILL pending after reconciliation → marked as 'failed' with polaris_export_failure
     *
     * What this method does (for display):
     * - Retrieves notifications with status = 'failed'
     * - AND active polaris_export_failure issue
     * - Groups into clusters (2+ failures at same date+hour)
     * - Provides pattern analysis to identify systematic issues
     *
     * @param int $daysBack Number of days to look back (default: 7, recommended: 30 for pattern detection)
     *
     * @return array Analysis data containing:
     *               - clusters (Collection): Hourly clusters with 2+ failures, each containing:
     *               - date (string): Date of cluster (Y-m-d)
     *               - hour (int): Hour of day (0-23)
     *               - time_range (string): 12-hour time range (e.g., "2:00-2:59 PM")
     *               - count (int): Number of failures in cluster
     *               - notification_types (array): Breakdown by notification type
     *               - sample_ids (array): Up to 5 notification IDs for investigation
     *               - day_of_week (int): Day of week (0=Sunday, 6=Saturday)
     *               - timestamp (string): ISO timestamp of first failure
     *               - successful_at_same_time (int): Count of successful emails at same hour
     *               - partial_failure (bool): True if some emails succeeded at same time
     *               - daily_summary (Collection): Complete daily timeline for chart visualization, containing:
     *               - date (string): Date (Y-m-d)
     *               - count (int): Total orphaned notifications for that day (includes zero-count days)
     *               - total_orphaned (int): Total count of orphaned notifications
     *               - pattern_analysis (array): Pattern detection results:
     *               - has_pattern (bool): True if significant pattern detected
     *               - description (string): Human-readable pattern description
     *               - primary_time (int): Most common hour of failures
     *               - primary_day (string): Most common day of week
     *               - affected_types (array): Notification types most affected
     *
     * @see OrphanedNotificationScope For query scope implementation
     */
    public function getOrphanedNotifications(int $daysBack = 7): array
    {
        return $this->orphanedNotificationQueryService->getOrphanedNotifications($daysBack);
    }

    /**
     * Process orphaned notifications (Polaris → LX Starter communication failures).
     *
     * This method should be called AFTER the import pipeline completes to ensure
     * that we only mark notifications as failed if they are STILL orphaned after
     * the latest import cycle has had a chance to reconcile delivery confirmations.
     *
     * A notification is considered orphaned if:
     * - It's an email notification (delivery_option_id = 2)
     * - It was exported from Polaris >24 hours ago
     * - It is STILL in 'pending' status (no delivery confirmation received)
     * - It was exported within the last N days (configured by $daysBack)
     *
     * This method:
     * 1. Updates notification status from 'pending' to 'failed'
     * 2. Sets failed_at timestamp
     * 3. Sets failure_reason explaining the issue
     * 4. Creates PatronDeliveryIssue records for tracking
     *
     * @param int $daysBack Number of days back to check for orphaned notifications (default 7)
     *
     * @return array Statistics about processed notifications (processed, issues_created, total_orphaned)
     */
    public function processOrphanedNotifications(int $daysBack = 7): array
    {
        // DISABLED as of 2026-01-04: Orphaned notification detection is now OBSOLETE
        // for Email/Mail notifications. All failure cases are handled by reconciliation
        // logic per AGENTS.md sacred rules:
        //
        // - NotificationLog + Summary Report both failure → failed
        // - NotificationLog success but NOT in Summary Report → LX Starter communication error
        // - notification_status_id NULL after 48 hours → Polaris error
        //
        // The old orphaned logic used first_exported_at which is UTC and doesn't match
        // the export filename datetime (configured timezone) that the reconciler now uses.
        //
        // Return empty results - no orphaned notifications detected
        return [
            'processed'      => 0,
            'issues_created' => 0,
            'total_orphaned' => 0,
        ];
    }

    /**
     * Get SMS opt-out failures for display on issues page.
     *
     * Returns notifications that failed due to SMS opt-out, grouped by patron.
     *
     * @param int $daysBack How many days back to check (default: 30)
     *
     * @return array Statistics and affected patrons
     */
    public function getSmsOptOutFailures(int $daysBack = 30): array
    {
        return $this->notificationDiagnosticsQueryService->getSmsOptOutFailures($daysBack);
    }

    /**
     * Get import status information for dashboard monitoring.
     *
     * Checks last import times for all expected data sources and determines
     * if imports are running on schedule based on the expected daily schedule:
     * - 5:00 AM: Notification Log & History
     * - 8:05 AM: First FTP batch (holds, renewal, overdue)
     * - 9:05 AM: Second holds batch
     * - 1:05 PM: Third holds batch
     * - 5:05 PM: Fourth holds batch
     *
     * @return array Import status with sources, failed files, and schedule adherence
     */
    public function getImportStatus(): array
    {
        return $this->importStatusService->getImportStatus();
    }
}
