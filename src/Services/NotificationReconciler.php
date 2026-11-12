<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Contracts\NotificationReconcilerInterface;
use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\DeliveryOption;
use Dcplibrary\Notices\Models\FailedDeliveryString;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationImportBatch;
use Dcplibrary\Notices\Models\NotificationType;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Dcplibrary\Notices\Models\PolarisNotificationHistory;
use Dcplibrary\Notices\Models\PolarisOverdueNotification;
use Dcplibrary\Notices\Models\PolarisRenewalNotification;
use Dcplibrary\Notices\Support\RawPayloadExtractor;
use Dcplibrary\Notices\Support\SearchNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles and deduplicates notifications across all imported data sources.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * CRITICAL: Reconciliation MUST run BEFORE marking any notifications as orphaned!
 *
 * Order of operations:
 * 1. Import FTP exports (Polaris notifications)
 * 2. Import email confirmations (LX Starter delivery status)
 * 3. Run reconciliation (THIS CLASS - matches confirmations to notifications)
 * 4. THEN mark still-pending notifications as orphaned
 *
 * If you mark as orphaned BEFORE reconciliation, you get false positives!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * This implementation handles hold notifications (NotificationTypeID 2, 18),
 * renewal/almost due notifications (NotificationTypeID 7), and overdue notifications.
 *
 * ## What Reconciliation Does
 *
 * 1. **Deduplication**: Groups raw Polaris exports into logical notifications
 * 2. **Status Updates**: Matches delivery confirmations to update notification status
 * 3. **Failure Detection**: Creates delivery issues for failed notifications
 * 4. **Date-Based Matching**: ONLY matches confirmations from same export date
 *
 * After reconciliation runs, any notifications still in "pending" status are candidates
 * for orphaned detection (but only if an import batch has run since their export).
 *
 * @see \Dcplibrary\Notices\Console\ImportCommand (calls reconciliation at line 110-113)
 * @see \Dcplibrary\Notices\Console\ProcessOrphanedNotificationsCommand (runs AFTER reconciliation)
 */
class NotificationReconciler implements NotificationReconcilerInterface
{
    /**
     * Patrons with NotificationLog/Summary Report rows this many days before the
     * import date get their Email/Mail notifications rebuilt.
     */
    protected const EMAIL_LOG_REBUILD_LOOKBACK_DAYS = 7;

    protected NotificationActivityLogger $activityLogger;

    public function __construct(NotificationActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * First-pass deduplication for hold and renewal notifications for a single date.
     *
     * Groups raw Polaris hold and renewal exports into logical notifications
     * using the following key (per day):
     *   patron_id + delivery_option_id + notification_type_id
     *
     * This approximates the specification "same patron, same time,
     * same delivery option" by treating all exports for a given day
     * as belonging to the same logical notification.
     *
     * The official notification timestamp is the first time it appears
     * in any exported file (min(export_datetime)).
     *
     * @param \DateTimeInterface $date date window used when deduplicating
     */
    public function deduplicate(\DateTimeInterface $date): void
    {
        $dateString = $date->format('Y-m-d');

        // Process holds (notification_type_id 2 or 18)
        // CRITICAL: Only process Voice (3) and SMS (8) from holds_ files.
        // Email (2) and Mail (1) notifications should ONLY be created from NotificationLog.
        $rawHolds = PolarisHoldNotification::query()
            ->whereIn('notification_type_id', [2, 18])
            ->whereIn('delivery_option_id', [3, 8]) // Voice and SMS only
            ->whereDate('export_datetime', $dateString)
            ->get()
        ;

        if ($rawHolds->isNotEmpty()) {
            $this->deduplicateNotifications($rawHolds, 'hold');
        }

        // Process renewals (notification_type_id 7)
        // CRITICAL: Only process Voice (3) and SMS (8) from renew_ files.
        // Email (2) and Mail (1) notifications should ONLY be created from NotificationLog.
        $rawRenewals = PolarisRenewalNotification::query()
            ->where('notification_type_id', 7)
            ->whereIn('delivery_option_id', [3, 8]) // Voice and SMS only
            ->whereDate('export_datetime', $dateString)
            ->get()
        ;

        if ($rawRenewals->isNotEmpty()) {
            $this->deduplicateNotifications($rawRenewals, 'renewal');
        }

        // Process overdues (notification_type_id 1, 8, 11, 12, 13)
        // CRITICAL: Only process Voice (3) and SMS (8) from overdue_ files.
        // Email (2) and Mail (1) notifications should ONLY be created from NotificationLog.
        $rawOverdues = PolarisOverdueNotification::query()
            ->whereIn('notification_type_id', [1, 8, 11, 12, 13])
            ->whereIn('delivery_option_id', [3, 8]) // Voice and SMS only
            ->whereDate('export_datetime', $dateString)
            ->get()
        ;

        if ($rawOverdues->isNotEmpty()) {
            $this->deduplicateNotifications($rawOverdues, 'overdue');
        }

        // Create Email/Mail notifications from NotificationLog
        // CRITICAL: Email (2) and Mail (1) notifications are ONLY created from NotificationLog.
        // They do NOT come from holds_/renew_/overdue_ files.
        $this->createEmailMailNotificationsFromLog($dateString);
    }

    /**
     * Backwards-compatible wrapper for older tests/callers.
     *
     * Historically, some code paths invoked a "reconcileHolds" method.
     * The reconciler now exposes a two-step flow:
     * - deduplicate($date)
     * - reconcile($date)
     *
     * This wrapper preserves the old entrypoint while delegating to the new flow.
     */
    public function reconcileHolds(\DateTimeInterface $date): void
    {
        $this->deduplicate($date);
        $this->reconcile($date);
    }

    /**
     * Reconcile unified notification records for a given date.
     *
     * Updates Notification.status, delivered_at, and failed_at using:
     * - NotificationLog-derived confirmations (delivery_confirmations rows)
     * - Shoutbomb failure emails ingested via Graph (delivery_confirmations rows)
     *
     * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     * 🖍️ NOTIFICATIONLOG IS GOD FOR EMAIL NOTIFICATIONS 🖍️
     * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     *
     * For Email/Mail notifications (delivery_option_id 1, 2):
     * - NotificationLog is the AUTHORITATIVE source of truth
     * - notification_status_id from Polaris determines final status:
     *   * Status 1 (Sent/Delivered) → notification marked as 'delivered'
     *   * Status 2 (Failed) or other → notification marked as 'failed' with reason from NotificationLog
     * - Summary Report is for VERIFICATION ONLY - does NOT override NotificationLog
     * - If Summary Report doesn't align with NotificationLog → Polaris/LX Starter system issue
     *
     * CRITICAL MATCHING RULES:
     * - Notifications are matched to delivery confirmations by THREE keys:
     *   1. patron_id (or patron_barcode if patron_id is NULL)
     *   2. delivery_option_id
     *   3. **DATE(first_exported_at) = DATE(notice_date)** ← MUST match dates!
     *
     * - This prevents notifications from being matched with confirmations from
     *   different dates (e.g., Jan 1 notification matching Dec 30 confirmation)
     *   which would cause delivered_at to be BEFORE first_exported_at.
     *
     * - For Email/Mail notifications, matching window is extended to +3 days to handle
     *   late-day exports that miss the next morning's 8 AM email batch.
     *
     * Delivery channel rules:
     * - For Mail (1)/Email (2): NotificationLog status is AUTHORITATIVE (see above)
     * - For Voice (3)/SMS (8): DO NOT use NotificationLog or Summary Reports
     *   * Created from holds_/overdue_/renew_ export files ONLY
     *   * Shoutbomb failure reports for failures ONLY (no NotificationLog)
     *   * Success-by-absence logic requires BOTH:
     *     - REQUIREMENT 1: 24-hour grace period passed since first_exported_at
     *     - REQUIREMENT 2: Import batch ran AFTER the 24-hour mark
     *   * This is "first-import-after-24h" logic - the first import after 24h determines status
     *   * Without both requirements, notifications marked delivered before failures arrive
     *
     * @param \DateTimeInterface $date Primary date to reconcile, also re-checks
     *                                 pending/failed notifications from last 30 days
     *                                 to handle late-arriving confirmations
     */
    public function reconcile(\DateTimeInterface $date): void
    {
        $dateString = $date->format('Y-m-d');
        // Use the requested reconcile date as the query anchor so historical
        // replays remain deterministic and do not depend on wall-clock "now".
        $reconcileAnchor = Carbon::parse($date->format('Y-m-d H:i:s'), $date->getTimezone());

        // Preload Shoutbomb report coverage by report_date so Voice/SMS success-by-absence
        // is ONLY applied when we know the corresponding daily reports have actually been
        // ingested for that notice_date. This guards against incorrectly marking Voice/SMS
        // as delivered when failure emails for that date have not yet been processed
        // (e.g., when running a narrow import window).
        $shoutbombReportTypeIds = \Dcplibrary\Notices\Models\EmailReportType::query()
            ->whereIn('code', ['shoutbomb_voice_failure', 'shoutbomb_invalid_phone', 'shoutbomb_voice_system_error', 'shoutbomb_mismatched_preference'])
            ->pluck('id')
            ->all()
        ;

        $shoutbombReportsByDate = [];
        if (!empty($shoutbombReportTypeIds)) {
            $shoutbombReportsByDate = \Dcplibrary\Notices\Models\IngestedEmail::query()
                ->whereIn('email_report_type_id', $shoutbombReportTypeIds)
                ->whereNotNull('report_date')
                ->toBase()
                ->distinct()
                ->pluck('report_date')
                ->mapWithKeys(fn ($reportDate) => [substr((string) $reportDate, 0, 10) => true])
                ->all()
            ;
        }

        // Select all notifications whose first_exported_at falls on this date
        // PLUS any pending/failed notifications from the last 30 days to catch
        // late-arriving delivery confirmations
        $notifications = Notification::query()
            ->where(function ($q) use ($dateString, $reconcileAnchor) {
                $q->whereDate('first_exported_at', $dateString)
                    ->orWhere(function ($subq) use ($reconcileAnchor) {
                        $subq->whereIn('status', ['pending', 'failed'])
                            ->where('first_exported_at', '>=', $reconcileAnchor->copy()->subDays(30))
                        ;
                    })
                ;
            })
            ->get()
        ;

        if ($notifications->isEmpty()) {
            return;
        }

        // 1) Pull NotificationLog-derived confirmations for EMAIL/MAIL ONLY (channels 1, 2).
        //
        // CRITICAL: NotificationLog and Summary Reports are ONLY for Email/Mail notifications.
        // Voice/SMS notifications (3, 8) DO NOT use these data sources.
        //
        // We load confirmations for ALL notifications we're reconciling (today's date + last 30 days pending).
        // This allows late-arriving confirmations to be matched with older pending notifications.
        //
        // Email notifications are sent at 8 AM daily. If a notification is exported after
        // the 8 AM batch has been queued (e.g., exported at 2 PM), it will miss that day's
        // batch and be sent the next day. Additionally, email notifications exported late
        // on one day may not be sent until 2 days later if there are processing delays.
        //
        // Example: Notification exported Dec 31 2:04 PM
        //   - Misses Jan 1 8 AM batch (already queued)
        //   - Sent in Jan 2 8 AM batch
        //   - Appears in Jan 2 summary report (received ~2 PM Jan 2)
        //   - Result: 2-day gap between export (Dec 31) and delivery confirmation (Jan 2)
        //
        // To handle this, we use a wider window: 31 days back (to cover last 30 days + buffer)
        $dateStart = $reconcileAnchor->copy()->subDays(31)->format('Y-m-d');
        $dateEnd   = $reconcileAnchor->copy()->addDays(3)->format('Y-m-d'); // +3 for future confirmations

        $logConfirmations = DeliveryConfirmation::query()
            ->whereIn('confirmation_source', [
                'notification_log',
                'polaris_summary_report_success',
                'polaris_summary_report_failure',
            ])
            ->whereIn('delivery_option_id', [1, 2]) // EMAIL/MAIL ONLY - NOT Voice/SMS
            ->whereBetween(DB::raw('DATE(notice_date)'), [$dateStart, $dateEnd])
            // Restrict to rows with holds_count >= 0 when present (holds batches).
            // CRITICAL: Use >= not > because holds_count can be 0 for valid notifications
            ->where(function ($q) {
                $q->whereNull('holds_count')
                    ->orWhere('holds_count', '>=', 0)
                ;
            })
            ->get()
        ;

        // Group NotificationLog by patron_id + delivery_option_id + date for quick lookup.
        // CRITICAL: Must include date to prevent matching confirmations from different dates.
        $logByPatronAndDo = $logConfirmations
            ->filter(fn (DeliveryConfirmation $dc) => 'notification_log' === $dc->confirmation_source)
            ->groupBy(function (DeliveryConfirmation $dc): string {
                $date = $dc->notice_date ? (new \DateTime($dc->notice_date))->format('Y-m-d') : '';

                return ($dc->patron_id ?? 0) . ':' . ($dc->delivery_option_id ?? 0) . ':' . $date;
            })
        ;

        // Group NotificationLog by patron_barcode for fallback lookup
        $logByBarcodeAndDo = $logConfirmations
            ->filter(fn (DeliveryConfirmation $dc) => 'notification_log' === $dc->confirmation_source && !empty($dc->patron_barcode))
            ->groupBy(function (DeliveryConfirmation $dc): string {
                $date = $dc->notice_date ? (new \DateTime($dc->notice_date))->format('Y-m-d') : '';

                return $dc->patron_barcode . ':' . ($dc->delivery_option_id ?? 0) . ':' . $date;
            })
        ;

        // Group Summary Reports WITHOUT patron/date first (per-patron lookups)
        // Summary Report notice_date = report date (typically the day AFTER the email was sent)
        // Match by patron_barcode + delivery_option ONLY; date-based gating is applied later.
        $summaryReportsByBarcode = $logConfirmations
            ->filter(
                fn (DeliveryConfirmation $dc) => in_array($dc->confirmation_source, ['polaris_summary_report_success', 'polaris_summary_report_failure'])
                && !empty($dc->patron_barcode)
            )
            ->groupBy(function (DeliveryConfirmation $dc): string {
                return $dc->patron_barcode . ':' . ($dc->delivery_option_id ?? 0);
            })
        ;

        // ALSO group Summary Reports by DATE so we can enforce the CRITICAL "hard gating"
        // rules from AGENTS.md:
        // - Email status MUST remain pending until a valid Summary Report exists for
        //   sent_date (DATE(NotificationLog.notice_date)).
        // - A "valid" report means: at least one row exists for that calendar date.
        $summaryReportsByDate = $logConfirmations
            ->filter(
                fn (DeliveryConfirmation $dc) => in_array($dc->confirmation_source, ['polaris_summary_report_success', 'polaris_summary_report_failure'])
                    && $dc->notice_date
            )
            ->groupBy(function (DeliveryConfirmation $dc): string {
                return (new \DateTime($dc->notice_date))->format('Y-m-d');
            })
        ;

        // 2) Pull Shoutbomb failure/invalid/system-error confirmations for this date range.
        $voiceFailures = DeliveryConfirmation::query()
            ->whereIn('confirmation_source', [
                'shoutbomb_voice_failure',
                'shoutbomb_invalid_phone',
                'shoutbomb_voice_system_error',
            ])
            ->whereBetween(DB::raw('DATE(notice_date)'), [$dateStart, $dateEnd])
            ->get()
        ;

        // Group failures by patron_barcode and by delivery_string (phone).
        $failuresByBarcode = $voiceFailures
            ->filter(fn (DeliveryConfirmation $dc) => !empty($dc->patron_barcode))
            ->groupBy('patron_barcode')
        ;

        $failuresByPhone = $voiceFailures
            ->filter(fn (DeliveryConfirmation $dc) => null !== SearchNormalizer::normalizePhone($dc->delivery_string))
            ->groupBy(fn (DeliveryConfirmation $dc) => SearchNormalizer::normalizePhone($dc->delivery_string))
        ;

        foreach ($notifications as $notification) {
            $channel = (int) $notification->delivery_option_id;

            $originalStatus        = $notification->status;
            $originalFailureReason = $notification->failure_reason;
            $originalDeliveredAt   = $notification->delivered_at;
            $originalFailedAt      = $notification->failed_at;

            $status        = $originalStatus;
            $failureReason = $originalFailureReason;
            $deliveredAt   = $originalDeliveredAt;
            $failedAt      = $originalFailedAt;

            $hasFailure                     = false;
            $hadFailedAttemptsForEmail      = false; // any failures or summary failures for this email notification
            $hadSummaryFailureForEmail      = false; // summary_report_failure on/after anchor date
            $hadSummarySuccessForEmail      = false; // summary_report_success on/after anchor date
            $hadNotificationLogFailureEmail = false; // NotificationLog failure status for this email

            // 2a) Voice/SMS: check Shoutbomb failure emails first (highest precedence).
            if (in_array($channel, [3, 8], true)) {
                $barcode = $notification->patron_barcode;

                $barcodeFailures = $barcode && isset($failuresByBarcode[$barcode])
                    ? $failuresByBarcode[$barcode]
                    : collect();

                // Some Shoutbomb reports (e.g. "Calls that generated errors") carry only
                // the phone number. Match those by phone, but only for this
                // notification's own export cycle: a phone number is shared across
                // notices, so an undated match would fail every later notice too.
                if ($barcodeFailures->isEmpty()) {
                    $barcodeFailures = $this->phoneFailuresForNotification($notification, $channel, $failuresByPhone);
                }

                if ($barcodeFailures->isNotEmpty()) {
                    $hasFailure = true;

                    $status   = 'failed';
                    $failed   = $barcodeFailures->first()->notice_date;
                    $failedAt = $failed ?: $failedAt ?: $notification->first_exported_at;

                    // Clear delivered_at when marking as failed
                    $deliveredAt = null;

                    // Keep the raw failure line as the reason for easier debugging.
                    $failureReason = $barcodeFailures->first()->details
                        ?: $failureReason
                        ?: 'Shoutbomb reported voice/SMS failure';

                    // Activity log: Shoutbomb failure matched to this notification
                    $firstFailure = $barcodeFailures->first();
                    $this->activityLogger->log(
                        $notification,
                        source: 'shoutbomb_failure',
                        description: 'Shoutbomb failure report matched to this notification',
                        occurredAt: $firstFailure->notice_date ?? $failedAt ?? now(),
                        meta: [
                            'confirmation_source' => $firstFailure->confirmation_source ?? null,
                            'delivery_string'     => $firstFailure->delivery_string     ?? null,
                            'details'             => $firstFailure->details             ?? null,
                        ],
                    );
                }
            }

            // 2b) CHANNEL-SPECIFIC RECONCILIATION LOGIC
            //
            // Email/Mail (1, 2): Sacred rules from AGENTS.md
            // Voice/SMS (3, 8): Use success-by-absence (24h grace period + import batch)
            if (!$hasFailure) {
                // EMAIL/MAIL ONLY: Apply sacred rules from AGENTS.md
                if (in_array($channel, [1, 2], true)) {
                    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                    // SACRED RULES FOR EMAIL/MAIL STATUS DETERMINATION
                    // See AGENTS.md for full documentation
                    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

                    // Get NotificationLog and Summary Report entries for this notification
                    // CRITICAL: For Email/Mail, NotificationLog.notice_date is the
                    // anchor for sent_date and Summary Report gating.
                    $notificationDate = $notification->first_exported_at
                        ? (new \DateTime($notification->first_exported_at))->format('Y-m-d')
                        : '';

                    $key     = ($notification->patron_id ?? 0) . ':' . ($channel ?? 0) . ':' . $notificationDate;
                    $logRows = $logByPatronAndDo[$key] ?? collect();

                    // Also try by barcode
                    if ($logRows->isEmpty() && !empty($notification->patron_barcode)) {
                        $barcodeKey = $notification->patron_barcode . ':' . ($channel ?? 0) . ':' . $notificationDate;
                        $logRows    = $logByBarcodeAndDo[$barcodeKey] ?? collect();
                    }

                    if ($logRows->isEmpty()) {
                        // No NotificationLog entry - this should never happen for Email/Mail
                        // Email/Mail notifications MUST exist in NotificationLog or they don't exist
                        continue; // Skip this notification
                    }

                    // Get LATEST NotificationLog entry by notice_date
                    $notificationLogEntry = $logRows
                        ->filter(fn ($row) => 'notification_log' === $row->confirmation_source)
                        ->sortByDesc('notice_date')
                        ->first()
                    ;

                    if (!$notificationLogEntry) {
                        // No NotificationLog entry found - skip
                        continue;
                    }

                    // Derive sent_date from NotificationLog.notice_date. This is the
                    // calendar date the email was sent for, and drives Summary Report
                    // gating per AGENTS.md.
                    $sentDate = null;
                    if ($notificationLogEntry->notice_date) {
                        $sentDate = (new \DateTime($notificationLogEntry->notice_date))->format('Y-m-d');
                    }

                    // For Email (2) ONLY, we must HARD-GATE reconciliation on Summary
                    // Report coverage for sent_date. If there is no valid Summary Report
                    // for sent_date (i.e. no rows for that date at all), this
                    // notification MUST remain pending regardless of how much time has
                    // passed.
                    $hasSummaryForSentDate = false;
                    $hasSummaryForNextDate = false;

                    if (2 === $channel && $sentDate) {
                        $hasSummaryForSentDate = isset($summaryReportsByDate[$sentDate]) && $summaryReportsByDate[$sentDate]->isNotEmpty();

                        $nextDate              = (new \DateTimeImmutable($sentDate))->modify('+1 day')->format('Y-m-d');
                        $hasSummaryForNextDate = isset($summaryReportsByDate[$nextDate]) && $summaryReportsByDate[$nextDate]->isNotEmpty();

                        if (!$hasSummaryForSentDate) {
                            // No valid Summary Report for sent_date yet (or only 0-row
                            // imports). Keep this email in pending status.
                            continue;
                        }
                    }
                    // Track if delivery_string is empty for Email - used later in status determination.
                    // Empty delivery_string alone does NOT immediately classify as system error;
                    // we must check Summary Report first to determine proper failure classification.
                    $hasEmptyDeliveryString = false;
                    if (2 === $channel) {
                        $logDeliveryString      = trim((string) ($notificationLogEntry->delivery_string ?? ''));
                        $hasEmptyDeliveryString = ('' === $logDeliveryString || 0 === strcasecmp($logDeliveryString, 'NULL'));
                    }

                    // Extract datetime from export filename stored on notification
                    // Format: notification_log_YYYY-MM-DD_HH-MM-SS.txt
                    $exportFilename     = $notification->export_filename;
                    $exportFileDatetime = null;

                    if ($exportFilename && preg_match('/notification_log_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})/', $exportFilename, $matches)) {
                        try {
                            // Parse using configured timezone (from config/notices.php)
                            $exportFileDatetime = new \DateTimeImmutable(
                                $matches[1] . ' ' . $matches[2] . ':' . $matches[3] . ':' . $matches[4],
                                new \DateTimeZone(config('notices.timezone', 'America/Chicago'))
                            );
                        } catch (\Throwable $e) {
                            // Parsing failed - log and continue without using exportFileDatetime
                            \Log::warning('Failed to parse export filename datetime', [
                                'filename' => $exportFilename,
                                'error'    => $e->getMessage(),
                            ]);
                            $exportFileDatetime = null;
                        }
                    }

                    // IMPORTANT: For Email/Mail (channels 1, 2), we no longer gate
                    // status updates behind a 24-hour grace period. As soon as
                    // NotificationLog + Summary Report data exist and the
                    // reconciliation command runs, status is determined from
                    // those sources. The 24-hour + first-import-after logic
                    // remains for Voice/SMS only.

                    // Past 24-hour grace period + first import has run
                    // Now apply status determination rules

                    $notificationStatusId = $notificationLogEntry->notification_status_id;

                    // Get Summary Report entry (if exists)
                    // Summary Reports are grouped WITHOUT date because they arrive the next day
                    // Look up by patron_barcode + delivery_option only
                    $summaryKey = $notification->patron_barcode . ':' . $channel;

                    // Choose the Summary Report entry that corresponds to THIS notification's
                    // export cycle, instead of always using the very first summary ever
                    // seen for this patron+channel.
                    //
                    // Strategy (dev-friendly, no retrofitting):
                    // - Take all summaries for patron_barcode + channel
                    // - Compute an anchor date for this notification based on the
                    //   LATEST NotificationLog notice_date (fallback: first_exported_at date)
                    // - Filter summaries to those with notice_date >= anchor date
                    // - From those, pick the EARLIEST (closest after export)
                    //
                    // This avoids a single early failure summary permanently poisoning
                    // all future notifications for the same patron+channel.
                    $summaryRows = $summaryReportsByBarcode->get($summaryKey) ?? collect();

                    // For EMAIL summary gating, we care specifically about rows on
                    // sent_date (DATE(NotificationLog.notice_date)). We still keep the
                    // broader set $summaryRows for LX Starter/system diagnostics.
                    $anchorDate = null;
                    if ($notificationLogEntry->notice_date) {
                        $anchorDate = (new \DateTime($notificationLogEntry->notice_date))->format('Y-m-d');
                    } elseif ($notification->first_exported_at) {
                        $anchorDate = (new \DateTime($notification->first_exported_at))->format('Y-m-d');
                    }

                    $summaryRowsForSentDate = collect();
                    if ($sentDate && $summaryRows->isNotEmpty()) {
                        $summaryRowsForSentDate = $summaryRows->filter(function ($row) use ($sentDate) {
                            if (!$row->notice_date) {
                                return false;
                            }

                            $rowDate = (new \DateTime($row->notice_date))->format('Y-m-d');

                            return $rowDate === $sentDate;
                        });
                    }

                    // Compute aggregates for success/failure presence on sent_date.
                    $hasSummarySuccess = $summaryRowsForSentDate->contains(function ($row) {
                        return 'polaris_summary_report_success' === $row->confirmation_source;
                    });
                    $hasSummaryFailure = $summaryRowsForSentDate->contains(function ($row) {
                        return 'polaris_summary_report_failure' === $row->confirmation_source;
                    });

                    // Track for activity/meta purposes
                    $hadSummarySuccessForEmail = $hasSummarySuccess;
                    $hadSummaryFailureForEmail = $hasSummaryFailure;

                    // Also compute "any time" aggregates for this patron+channel so we
                    // can distinguish true "Summary Report missing entirely" cases
                    // (LX Starter communication errors) from cases where a success
                    // exists on a nearby day but did not line up exactly with the
                    // anchor date.
                    $hasAnySummarySuccess = $summaryRows->contains(function ($row) {
                        return 'polaris_summary_report_success' === $row->confirmation_source;
                    });
                    $hasAnySummaryFailure = $summaryRows->contains(function ($row) {
                        return 'polaris_summary_report_failure' === $row->confirmation_source;
                    });

                    // Keep a single representative summary entry for timestamps/details.
                    // Prefer an entry on sent_date when available, otherwise
                    // fall back to the earliest summary for this patron+channel.
                    $summaryReportEntry = null;
                    if ($summaryRowsForSentDate->isNotEmpty()) {
                        $summaryReportEntry = $summaryRowsForSentDate
                            ->sortBy('notice_date')
                            ->first()
                        ;
                    } elseif ($summaryRows->isNotEmpty()) {
                        $summaryReportEntry = $summaryRows
                            ->sortBy('notice_date')
                            ->first()
                        ;
                    }

                    // Activity log: summary report matched to this notification (if any)
                    if ($summaryReportEntry) {
                        $summaryActivityFile = null;
                        if ($summaryReportEntry->ingested_email_id ?? null) {
                            $summaryActivityFile = ActivityFile::where('type', 'summary_report_email')
                                ->where('metadata->ingested_email_id', $summaryReportEntry->ingested_email_id)
                                ->first()
                            ;
                        }

                        $this->activityLogger->log(
                            $notification,
                            source: 'summary_report',
                            description: 'Summary report matched to this notification',
                            file: $summaryActivityFile,
                            occurredAt: $summaryReportEntry->notice_date ?? $summaryReportEntry->received_at ?? now(),
                            meta: [
                                'confirmation_source' => $summaryReportEntry->confirmation_source ?? null,
                                'details'             => $summaryReportEntry->details             ?? null,
                                'ingested_email_id'   => $summaryReportEntry->ingested_email_id   ?? null,
                            ],
                        );
                    }

                    // STATUS DETERMINATION:
                    //
                    // IMPORTANT: Success/failure codes are CHANNEL-AWARE.
                    // We do NOT treat the same notification_status_id as success for
                    // all delivery options.
                    //
                    // EMAIL (delivery_option_id = 2)
                    //   Success codes:
                    //     12 = Email Completed
                    //     16 = Sent
                    //   Failure codes:
                    //     13 = Email Failed - Invalid address
                    //     14 = Email Failed
                    //   Notes:
                    //   - 7 (Delivered (Email)) is NOT treated as final success here
                    //     because its description is a "Call ..." status and does not
                    //     represent a fully completed LX Starter email.
                    //   - 6 (Failed - Invalid Email) and 17 (Email address missing for
                    //     LX Starter) are not currently used in the Email reconciliation
                    //     buckets; if Polaris starts using them consistently for Email,
                    //     we can revisit and add explicit handling.
                    //
                    // MAIL (delivery_option_id = 1)
                    //   Success codes:
                    //     15 = Mail Printed
                    //   Pending:
                    //     NULL notification_status_id (or 0) means "still pending".
                    //   Any other non-NULL status code does NOT currently imply
                    //   success or failure for Mail; status remains 'pending' unless
                    //   15 is present.
                    if (2 === $channel) { // Email
                        $notificationLogSuccess = in_array($notificationStatusId, [12, 16], true);
                        $notificationLogFailure = in_array($notificationStatusId, [13, 14], true);

                        $hadNotificationLogFailureEmail = $notificationLogFailure;
                    } elseif (1 === $channel) { // Mail
                        $notificationLogSuccess = (15 === $notificationStatusId);
                        // No mail-specific failure codes are modeled yet.
                        $notificationLogFailure = false;
                    } else {
                        // Voice/SMS and any other channels: NotificationLog status codes
                        // are not used for success/failure. Those channels use
                        // Shoutbomb/PhoneNotices logic instead.
                        $notificationLogSuccess = false;
                        $notificationLogFailure = false;
                    }

                    // NULL status in NotificationLog - Polaris error case
                    $notificationLogNull = (null === $notificationStatusId || 0 === $notificationStatusId);

                    if ($notificationLogNull && 2 === $channel) {
                        // NULL notification_status_id - wait ANOTHER 24 hours (EMAIL ONLY)
                        if ($exportFileDatetime) {
                            $secondGracePeriodEnd = $exportFileDatetime->add(new \DateInterval('PT48H')); // 48 hours total

                            if (now() < $secondGracePeriodEnd) {
                                // Still in second 24-hour wait period
                                continue; // Keep as pending
                            }

                            // Check if import ran after second grace period
                            $importAfterSecondGracePeriod = NotificationImportBatch::query()
                                ->where('imported_at', '>', $secondGracePeriodEnd)
                                ->exists()
                            ;

                            if (!$importAfterSecondGracePeriod) {
                                // No import after second grace period yet
                                continue; // Keep as pending
                            }

                            // Still NULL after 48 hours + 2 import cycles → Polaris error
                            $status        = 'failed';
                            $failureReason = 'Polaris error: notification_status_id remained NULL after 48 hours';
                            $failedAt      = $noticeDate ?: now();
                            $deliveredAt   = null;
                        }
                    }
                    // MAIL-SPECIFIC HANDLING: Summary Reports do not apply to Mail.
                    // If Mail has a success code (15), we treat it as delivered using
                    // NotificationLog alone. NULL stays pending; other codes are
                    // currently ignored (treated as pending) until a clear rule is
                    // defined for them.
                    elseif (1 === $channel) {
                        if ($notificationLogSuccess) {
                            $status        = 'delivered';
                            $failureReason = null;
                            $deliveredAt   = $notificationLogEntry->notice_date ?: $notification->first_exported_at;
                            $failedAt      = null;
                        }
                    // For Mail, there is no LX Starter, no Summary Report, and no
                    // modeled failure codes yet. If we didn't have success, we
                    // leave the notification as pending.
                    } elseif (
                        $notificationLogSuccess
                        && (
                            // Ideal case: Summary Report success on the anchor date
                            $hasSummarySuccess
                            // Fallback: if there are *no* failures anywhere for this
                            // patron+channel but we do have a success Summary Report
                            // at some point (e.g. date skew), treat it as delivered
                            // instead of an LX Starter communication error.
                            || (
                                !$hasSummaryFailure
                                && !$hasAnySummaryFailure
                                && $summaryReportEntry
                                && 'polaris_summary_report_success' === $summaryReportEntry->confirmation_source
                            )
                        )
                    ) {
                        // NotificationLog AND Summary Report indicate success → DELIVERED
                        $status        = 'delivered';
                        $failureReason = null;
                        $deliveredAt   = $notificationLogEntry->notice_date ?: $notification->first_exported_at;
                        $failedAt      = null;
                    } elseif ($notificationLogFailure && $hasSummaryFailure) {
                        // BOTH NotificationLog AND at least one Summary Report say failure → FAILED
                        $status        = 'failed';
                        $failureReason = $notificationLogEntry->details
                            ?: ($summaryReportEntry->details ?? null)
                            ?: 'Email/Mail notification failed (per NotificationLog and Summary Report)';
                        $failedAt    = $notificationLogEntry->notice_date ?: $notification->first_exported_at;
                        $deliveredAt = null;
                    } elseif (
                        $notificationLogSuccess
                        && 2 === $channel
                        && $sentDate
                        && $hasSummaryForSentDate
                        && $hasSummaryForNextDate
                        && !$hasSummarySuccess
                        && !$hasSummaryFailure
                    ) {
                        // We have valid Summary Reports for sent_date and the NEXT day,
                        // but this email never appeared in the report for sent_date.
                        // Treat this as a failure of the email for that cycle.
                        $status        = 'failed';
                        $failureReason = 'Email failed: notification missing from Summary Reports for sent_date after next-day report imported';
                        $failedAt      = $notificationLogEntry->notice_date ?: $notification->first_exported_at;
                        $deliveredAt   = null;
                    } elseif (
                        $notificationLogSuccess
                        && 2 === $channel
                        && !$hasSummarySuccess
                        && !$hasSummaryFailure
                        && !$hasAnySummarySuccess
                        && !$hasAnySummaryFailure
                    ) {
                        // NotificationLog says success BUT there are no Summary Report
                        // entries at all (for any date) for this patron+channel
                        // → LX STARTER COMMUNICATION ERROR (EMAIL ONLY)
                        $status        = 'failed';
                        $failureReason = 'LX Starter communication error: NotificationLog shows success but notification not found in Summary Report';
                        $failedAt      = $notificationLogEntry->notice_date ?: $notification->first_exported_at;
                        $deliveredAt   = null;
                    } elseif ($notificationLogFailure && $hasSummarySuccess) {
                        // NotificationLog says failure BUT at least one Summary Report says success
                        // Trust the Summary Report (actual delivery confirmation) over NotificationLog
                        $status        = 'delivered';
                        $failureReason = null;
                        $deliveredAt   = $summaryReportEntry->notice_date ?: $notification->first_exported_at;
                        $failedAt      = null;
                    } elseif ($hasSummaryFailure) {
                        // Summary Report shows failure - mark as failed regardless of NotificationLog
                        $status        = 'failed';
                        $failureReason = $summaryReportEntry->details ?: 'Email/Mail notification failed (per Summary Report)';
                        $failedAt      = $summaryReportEntry->notice_date ?: $notification->first_exported_at;
                        $deliveredAt   = null;
                    } elseif ($hasEmptyDeliveryString && 2 === $channel) {
                        // Empty delivery_string for Email WITH NO Summary Report confirmation.
                        // This is a Polaris/LX Starter system error - email address was missing.
                        // Note: If Summary Report also showed failure, that case is handled above
                        // and uses the Summary Report's failure reason instead.
                        $status        = 'failed';
                        $failureReason = 'Polaris/LX Starter error: NotificationLog delivery_string is empty (no email address)';
                        $failedAt      = $notificationLogEntry->notice_date ?: ($notification->first_exported_at ?: now());
                        $deliveredAt   = null;

                        // Create issue for tracking (auto-resolved since it's a system diagnostic)
                        $existingIssue = PatronDeliveryIssue::query()
                            ->where('issue_type', 'polaris_export_failure')
                            ->where('notification_id', $notification->id)
                            ->whereNull('resolved_at')
                            ->first()
                        ;

                        if (!$existingIssue) {
                            $detectedAt = $failedAt ?: now();
                            PatronDeliveryIssue::create([
                                'issue_type'      => 'polaris_export_failure',
                                'patron_id'       => $notification->patron_id,
                                'patron_barcode'  => $notification->patron_barcode,
                                'notification_id' => $notification->id,
                                'issue_details'   => trim(sprintf(
                                    'Polaris/LX Starter error: NotificationLog delivery_string was empty (no email address). status_id=%s; file=%s',
                                    (string) ($notificationLogEntry->notification_status_id ?? ''),
                                    (string) ($notificationLogEntry->source_filename ?? '')
                                )),
                                'detected_at'      => $detectedAt,
                                'resolved_at'      => $detectedAt,
                                'resolution_notes' => 'Auto-resolved: Polaris data error (missing email address)',
                                'resolved_by_name' => 'system',
                            ]);
                        }
                    }

                    // For email, track if there were *any* failed attempts or
                    // negative signals even if the final status ended up
                    // delivered. This is useful for UI explanations and
                    // support/debug exports.
                    if (2 === $channel) {
                        $hadFailedAttemptsForEmail = (
                            $hadNotificationLogFailureEmail
                            || $hadSummaryFailureForEmail
                            || $hasAnySummaryFailure
                        );
                    }
                // Any other case - remain pending (shouldn't happen but safe fallback)
                // This covers edge cases where we can't determine status
                }
                // VOICE/SMS (3, 8): Success-by-absence logic with 24h grace period
                elseif (in_array($channel, [3, 8], true)) {
                    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                    // CRITICAL: 24-Hour + First-Import-After Detection for Voice/SMS
                    //
                    // THE CLOCK STARTS FROM THE DATETIME IN THE EXPORT FILENAME
                    // (holds_/renew_/overdue_ filename in configured timezone)
                    //
                    // THE FIRST IMPORT THAT RUNS AFTER 24 HOURS DETERMINES SUCCESS/FAILURE.
                    //
                    // Voice/SMS notifications CANNOT be verified same-day because:
                    // 1. Shoutbomb sends failure reports the NEXT MORNING (~6 AM)
                    // 2. Success-by-absence means: if patron doesn't appear in failure report, delivery succeeded
                    // 3. A notification exported today won't have failure reports until tomorrow
                    //
                    // Success-by-absence is ONLY applied if BOTH conditions are met:
                    //
                    // REQUIREMENT 1: Notification must be 24+ hours old from export filename datetime (grace period)
                    // REQUIREMENT 2: An import batch must have run AFTER the 24-hour mark
                    //
                    // TIMELINE EXAMPLE:
                    // - holds_2026-01-06_09-05-01.txt exported (filename datetime = 9:05:01 AM Mon CST)
                    // - Clock starts: Mon 9:05:01 AM CST
                    // - 3:00 PM Mon: Import runs (6 hrs) → SKIP (not 24 hours yet)
                    // - 10:00 AM Tue: Import runs (25 hrs) → CHECK NOW (first import after 24h)
                    //   - If no failure report → mark as delivered
                    //   - If failure report exists → already marked as failed (lines 520-548)
                    //
                    // WHY BOTH CONDITIONS ARE REQUIRED:
                    // - 24-hour check ensures Shoutbomb had time to send failure reports
                    // - Import-after-24h ensures failure reports have been ingested and processed
                    // - First import after 24h is the determinant, not continuous time checking
                    // - Prevents false positives in dev environments with infrequent imports
                    //
                    // See: AGENTS.md for complete rules
                    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

                    // Extract datetime from export filename (holds_/renew_/overdue_)
                    // Format: holds_YYYY-MM-DD_HH-MM-SS.txt
                    $exportFilename     = $notification->export_filename;
                    $exportFileDatetime = null;

                    if ($exportFilename && preg_match('/(holds|renew|overdue)_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})/', $exportFilename, $matches)) {
                        try {
                            // Parse using configured timezone (from config/notices.php)
                            $exportFileDatetime = new \DateTimeImmutable(
                                $matches[2] . ' ' . $matches[3] . ':' . $matches[4] . ':' . $matches[5],
                                new \DateTimeZone(config('notices.timezone', 'America/Chicago'))
                            );
                        } catch (\Throwable $e) {
                            // Parsing failed - log and fall back to first_exported_at
                            \Log::warning('Failed to parse Voice/SMS export filename datetime', [
                                'filename' => $exportFilename,
                                'error'    => $e->getMessage(),
                            ]);
                        }
                    }

                    // Fallback to first_exported_at if filename parsing failed
                    if (!$exportFileDatetime && $notification->first_exported_at) {
                        $exportFileDatetime = new \DateTimeImmutable($notification->first_exported_at);
                    }

                    // Calculate grace period: 24 hours after export filename datetime
                    $gracePeriodEnd = $exportFileDatetime
                        ? $exportFileDatetime->add(new \DateInterval('PT24H'))
                        : null;

                    // REQUIREMENT 1: Grace period must have passed (24+ hours from filename datetime)
                    $gracePeriodPassed = $gracePeriodEnd && now() >= $gracePeriodEnd;

                    // REQUIREMENT 2: An import batch must have run AFTER the 24-hour mark
                    $importAfterGracePeriod = $gracePeriodPassed && NotificationImportBatch::query()
                        ->where('date_for', '>=', $exportFileDatetime ? $exportFileDatetime->format('Y-m-d') : null)
                        ->where('imported_at', '>', $gracePeriodEnd)
                        ->exists()
                    ;

                    // REQUIREMENT 3: Shoutbomb reports for this notice_date have been ingested.
                    // We approximate this by requiring at least one IngestedEmail with a
                    // Shoutbomb report_type and report_date matching the export/notice date.
                    $reportDate = $exportFileDatetime ? $exportFileDatetime->format('Y-m-d') : ($notification->first_exported_at
                        ? (new \DateTimeImmutable($notification->first_exported_at))->format('Y-m-d')
                        : null);

                    $reportsIngestedForDay = false;
                    if ($reportDate && isset($shoutbombReportsByDate[$reportDate])) {
                        $reportsIngestedForDay = true;
                    }

                    // ALL conditions must be true to mark as delivered
                    if ($gracePeriodPassed && $importAfterGracePeriod && $reportsIngestedForDay) {
                        $status = 'delivered';
                        // Use export filename datetime as delivered_at (when notification was sent to Shoutbomb)
                        $deliveredAt = $deliveredAt ?: ($exportFileDatetime ? $exportFileDatetime->format('Y-m-d H:i:s') : $notification->first_exported_at);
                    } else {
                        // Grace period not passed OR no import after grace period yet
                        // CRITICAL: Clear delivered_at even if status is already pending
                        // This prevents IntegrityCheckCommand from "fixing" pending notifications back to delivered
                        if ('delivered' === $status) {
                            $status = 'pending';
                        }
                        // Always clear delivered_at if grace period not met (even if status already pending)
                        if (null !== $deliveredAt) {
                            $deliveredAt = null;
                        }
                    }
                }
            }

            // Persist changes if anything actually shifted.
            $dirty = $status      !== $originalStatus
                || $failureReason !== $originalFailureReason
                || $deliveredAt != $originalDeliveredAt
                || $failedAt    != $originalFailedAt;

            if ($dirty) {
                $notification->status         = $status;
                $notification->failure_reason = $failureReason;
                $notification->delivered_at   = $deliveredAt;
                $notification->failed_at      = $failedAt;
                $notification->save();

                // Record a normalized activity log entry for this status change.
                // We currently focus on email/mail changes driven by NotificationLog
                // and Summary Reports, but this will also capture Voice/SMS
                // transitions from pending → delivered/failed.
                $descriptionParts = [];
                if ($originalStatus !== $status) {
                    $descriptionParts[] = sprintf('Status %s → %s', $originalStatus ?? 'null', $status ?? 'null');
                }
                if ($originalDeliveredAt != $deliveredAt && $deliveredAt) {
                    $descriptionParts[] = 'delivered_at updated';
                }
                if ($originalFailedAt != $failedAt && $failedAt) {
                    $descriptionParts[] = 'failed_at updated';
                }

                $description = $descriptionParts
                    ? implode('; ', $descriptionParts)
                    : 'Notification reconciliation updated delivery status.';

                $meta = [
                    'original_status'         => $originalStatus,
                    'new_status'              => $status,
                    'original_failure_reason' => $originalFailureReason,
                    'new_failure_reason'      => $failureReason,
                    'channel'                 => (int) $notification->delivery_option_id,
                ];

                // For email notifications, include extra context about
                // whether any failures/summary failures were observed.
                if (2 === (int) $notification->delivery_option_id) {
                    $meta['had_failed_attempts']      = $hadFailedAttemptsForEmail;
                    $meta['had_summary_failure']      = $hadSummaryFailureForEmail;
                    $meta['had_summary_success']      = $hadSummarySuccessForEmail;
                    $meta['had_log_failure']          = $hadNotificationLogFailureEmail;
                    $meta['summary_failure_any_time'] = $hasAnySummaryFailure ?? false;
                    $meta['summary_success_any_time'] = $hasAnySummarySuccess ?? false;
                }

                // When this status change is driven by NotificationLog for
                // email/mail (channels 1,2), try to link it to the
                // corresponding ActivityFile so the Activity Log UI can
                // pivot directly to the underlying log file.
                $activityFile = null;
                if (in_array((int) $notification->delivery_option_id, [1, 2], true) && $notification->export_filename) {
                    $activityFile = ActivityFile::where('type', 'notification_log')
                        ->where('original_name', $notification->export_filename)
                        ->first()
                    ;
                }

                $this->activityLogger->log(
                    $notification,
                    source: in_array((int) $notification->delivery_option_id, [1, 2], true)
                        ? 'notification_log'
                        : 'reconciliation',
                    description: $description,
                    file: $activityFile,
                    occurredAt: now(),
                    meta: $meta,
                );
            }
        }

        // Flag delivery issues (NULL preferences, failed delivery strings, LX Starter failures)
        // Note: Reload notifications to get updated status after reconciliation
        $notifications = Notification::query()
            ->whereDate('first_exported_at', $dateString)
            ->get()
        ;

        $this->flagDeliveryIssues($date, $notifications, $voiceFailures);
    }

    /**
     * Upsert day-scoped Email/Mail notifications for NotificationLog confirmations.
     *
     * @param \Illuminate\Support\Collection<int, DeliveryConfirmation> $logEntries
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function rebuildEmailMailNotificationGroups($logEntries): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        if ($logEntries->isEmpty()) {
            return $stats;
        }

        // One logical combined email/mail per patron + channel + type + calendar day.
        $groups = $logEntries->groupBy(function ($entry) {
            $noticeDate = $entry->notice_date
                ? Carbon::parse($entry->notice_date)->toDateString()
                : 'unknown';

            return implode(':', [
                $entry->patron_id            ?? '0',
                $entry->delivery_option_id   ?? '0',
                $entry->notification_type_id ?? '0',
                $noticeDate,
            ]);
        });

        DB::transaction(function () use ($groups, &$stats) {
            foreach ($groups as $key => $group) {
                /** @var DeliveryConfirmation $first */
                $first = $group->first();

                // Skip email notifications with empty delivery_string if the patron's
                // actual preference (from FTP exports) is Voice or SMS.
                // These are noise: Polaris tried to send email but patron prefers Voice/SMS.
                if (2 === $first->delivery_option_id) {
                    $deliveryString         = trim($first->delivery_string ?? '');
                    $hasEmptyDeliveryString = '' === $deliveryString || 'NULL' === strtoupper($deliveryString);

                    if ($hasEmptyDeliveryString && $first->patron_barcode) {
                        $ftpPreference = $this->getPatronFtpDeliveryPreference($first->patron_barcode);

                        // If FTP shows Voice (3) or SMS (8), skip this email notification
                        if (in_array($ftpPreference, [3, 8], true)) {
                            ++$stats['skipped'];

                            continue;
                        }
                    }
                }

                // Use notice_date as the generated/exported timestamp
                $generatedAt = $group
                    ->pluck('notice_date')
                    ->filter()
                    ->map(fn ($v) => Carbon::parse($v))
                    ->min()
                ;

                $firstExportAt = $generatedAt; // For Email/Mail, export = generation
                $lastExportAt  = $group
                    ->pluck('notice_date')
                    ->filter()
                    ->map(fn ($v) => Carbon::parse($v))
                    ->max()
                ;

                $noticeDay = $generatedAt ? $generatedAt->toDateString() : null;

                // Store NotificationLog entries as base raw payload for UI enrichment
                $rawPayload = $group
                    ->map(static function ($entry) {
                        return $entry->toArray();
                    })
                    ->values()
                    ->all()
                ;

                // Optional enrichment: attempt to merge item-level data (title, item_barcode)
                // from the original Polaris FTP exports (renewal/overdue). This helps cases
                // where notifications were created from NotificationLog only and would
                // otherwise lack item metadata in raw_payload.
                $enrichedRows = $this->enrichEmailNotificationPayloadFromExports($first, $rawPayload);
                if ($enrichedRows) {
                    $rawPayload = $enrichedRows;
                }

                // Merge summary report confirmations for this patron/type/channel near the
                // notice day (not all-time) so payload enrichment stays bounded.
                if ($noticeDay) {
                    $summaryStart = Carbon::parse($noticeDay)->subDay()->startOfDay();
                    $summaryEnd   = Carbon::parse($noticeDay)->addDays(3)->endOfDay();

                    $summaryReportEntries = DeliveryConfirmation::query()
                        ->whereIn('confirmation_source', ['polaris_summary_report_success', 'polaris_summary_report_failure'])
                        ->where(function ($q) use ($first) {
                            if ($first->patron_id) {
                                $q->where('patron_id', $first->patron_id);
                            }
                            if ($first->patron_barcode) {
                                $q->orWhere('patron_barcode', $first->patron_barcode);
                            }
                        })
                        ->where('delivery_option_id', $first->delivery_option_id)
                        ->where('notification_type_id', $first->notification_type_id)
                        ->whereBetween('notice_date', [$summaryStart, $summaryEnd])
                        ->get()
                    ;

                    if ($summaryReportEntries->isNotEmpty()) {
                        foreach ($summaryReportEntries as $summaryEntry) {
                            $rawPayload[] = $summaryEntry->toArray();
                        }
                    }
                }

                // Derive notification_key for upsert (includes notice day)
                $notificationKey = sha1($key);

                // Sanitize UTF-8
                $rawPayload = $this->sanitizeUtf8($rawPayload);
                $searchData = $this->buildNotificationSearchColumns($rawPayload);

                // Resolve notification type description
                $notificationTypeDescription = null;
                if (null !== $first->notification_type_id) {
                    $type                        = NotificationType::find($first->notification_type_id);
                    $notificationTypeDescription = $type?->description;
                }

                // Get the first (earliest) NotificationLog filename for grace period clock
                $exportFilename = $group
                    ->sortBy('notice_date')
                    ->pluck('source_filename')
                    ->filter()
                    ->first()
                ;

                // Preserve reconcile-owned status fields on rebuild. Only brand-new
                // notifications start as pending.
                $notification = Notification::firstOrNew(['notification_key' => $notificationKey]);
                $isNew        = !$notification->exists;

                $notification->fill([
                    'patron_id'            => $first->patron_id,
                    'patron_barcode'       => $first->patron_barcode,
                    'organization_id'      => $first->reporting_org_id,
                    'delivery_option_id'   => $first->delivery_option_id,
                    'notification_type_id' => $first->notification_type_id, // CRITICAL: Required for reconciliation matching
                    'notification_type'    => $notificationTypeDescription,
                    'notice_date'          => $generatedAt, // CRITICAL: Required for reconciliation matching
                    'schedule_step'        => null,
                    'generated_at'         => $generatedAt,
                    'first_exported_at'    => $firstExportAt,
                    'last_exported_at'     => $lastExportAt,
                    'export_filename'      => $exportFilename, // For Email/Mail grace period clock
                    'is_combined'          => $group->count() > 1,
                    'raw_payload'          => json_encode($rawPayload),
                    // Display columns: original case for UI
                    'display_patron_first_name' => $searchData['display_patron_first_name'],
                    'display_patron_last_name'  => $searchData['display_patron_last_name'],
                    'display_title'             => $searchData['display_title'],
                    'display_item_barcode'      => $searchData['display_item_barcode'],
                    // Search columns: normalized for matching
                    'search_patron_first_name' => $searchData['search_patron_first_name'],
                    'search_patron_last_name'  => $searchData['search_patron_last_name'],
                    'search_full_name'         => $searchData['search_full_name'],
                    'search_title'             => $searchData['search_title'],
                    'search_item_barcode'      => $searchData['search_item_barcode'],
                    'indexed_patron_phone'     => $searchData['indexed_patron_phone'],
                    'indexed_patron_email'     => $searchData['indexed_patron_email'],
                ]);

                if ($isNew) {
                    $notification->status         = 'pending';
                    $notification->failure_reason = null;
                    $notification->failed_at      = null;
                    $notification->delivered_at   = null;
                }

                $notification->save();
                ++$stats[$isNew ? 'created' : 'updated'];

                // NotificationLog-derived payloads often lack item/title information.
                // When possible, backfill title from Polaris NotificationHistory so the list view
                // can display an item title without expensive per-row lookups.
                if (!$notification->display_title && $notification->first_exported_at) {
                    $dayStart = Carbon::parse($notification->first_exported_at)->startOfDay();
                    $dayEnd   = (clone $dayStart)->addDay();

                    $history = PolarisNotificationHistory::query()
                        ->where('patron_id', $notification->patron_id)
                        ->where('notification_type_id', $notification->notification_type_id)
                        ->where('delivery_option_id', $notification->delivery_option_id)
                        ->where('notice_date', '>=', $dayStart)
                        ->where('notice_date', '<', $dayEnd)
                        ->orderByDesc('notice_date')
                        ->orderByDesc('id')
                        ->first()
                    ;

                    $title = $history?->title ? trim((string) $history->title) : null;

                    if ($title) {
                        if (mb_strlen($title) > 191) {
                            $title = mb_substr($title, 0, 191);
                        }

                        $notification->update([
                            'display_title' => $title,
                            'search_title'  => SearchNormalizer::normalizeTitle($title),
                        ]);
                    }
                }

                // Activity log: creation or update from NotificationLog
                // Include the source filename so the Activity Log can
                // reference the exact NotificationLog export file.

                // For activity timeline clarity, prefer the actual export
                // filename timestamp when it follows the standard pattern,
                // falling back to notice_date when parsing fails.
                $occurredAt = $generatedAt ?? now();

                if ($exportFilename
                    && preg_match(
                        '/notification_log_(\d{4}-\d{2}-\d{2})_(\d{2})-(\d{2})-(\d{2})\.txt/i',
                        $exportFilename,
                        $m,
                    )
                ) {
                    try {
                        $occurredAt = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            sprintf('%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4]),
                            'UTC',
                        )->setTimezone(config('app.timezone'));
                    } catch (\Throwable $e) {
                        // If parsing fails, keep the generatedAt-based fallback.
                    }
                }

                $this->activityLogger->log(
                    $notification,
                    source: 'notification_log_import',
                    description: sprintf(
                        'Notification %s from NotificationLog export file %s',
                        $isNew ? 'created' : 'updated',
                        $exportFilename ?? '(unknown file)'
                    ),
                    occurredAt: $occurredAt,
                    meta: [
                        'dedupe_key'        => $notificationKey,
                        'file'              => $exportFilename,
                        'group_size'        => $group->count(),
                        'notification_type' => $notificationTypeDescription,
                    ],
                );
            }
        });

        return $stats;
    }

    /**
     * Build the legacy (pre date-scoped) Email/Mail notification_key.
     *
     * Used only to prune collapsed forever-keys after day-scoped rebuilds.
     */
    public static function legacyEmailMailNotificationKey(
        int|string|null $patronId,
        int|string|null $deliveryOptionId,
        int|string|null $notificationTypeId,
    ): string {
        return sha1(implode(':', [
            $patronId           ?? '0',
            $deliveryOptionId   ?? '0',
            $notificationTypeId ?? '0',
        ]));
    }

    /**
     * Build the day-scoped Email/Mail notification_key.
     */
    public static function emailMailNotificationKey(
        int|string|null $patronId,
        int|string|null $deliveryOptionId,
        int|string|null $notificationTypeId,
        string $noticeDate,
    ): string {
        return sha1(implode(':', [
            $patronId           ?? '0',
            $deliveryOptionId   ?? '0',
            $notificationTypeId ?? '0',
            $noticeDate,
        ]));
    }

    /**
     * Shoutbomb failures matched by phone number for one Voice/SMS notification.
     *
     * Only failures reported for the notification's export date (or the following
     * day, for reports that straddle midnight) count, and only for the same channel
     * when the report states one.
     *
     * @param \Illuminate\Support\Collection $failuresByPhone failures keyed by digits-only phone
     */
    protected function phoneFailuresForNotification(Notification $notification, int $channel, $failuresByPhone): \Illuminate\Support\Collection
    {
        $phone = SearchNormalizer::normalizePhone($notification->indexed_patron_phone);

        if (!$phone || !isset($failuresByPhone[$phone]) || !$notification->first_exported_at) {
            return collect();
        }

        $exportDate = Carbon::parse($notification->first_exported_at)->toDateString();
        $nextDate   = Carbon::parse($exportDate)->addDay()->toDateString();

        return $failuresByPhone[$phone]
            ->filter(function (DeliveryConfirmation $dc) use ($channel, $exportDate, $nextDate) {
                if ($dc->delivery_option_id && (int) $dc->delivery_option_id !== $channel) {
                    return false;
                }

                if (!$dc->notice_date) {
                    return false;
                }

                $noticeDate = Carbon::parse($dc->notice_date)->toDateString();

                return $noticeDate === $exportDate || $noticeDate === $nextDate;
            })
            ->values()
        ;
    }

    /**
     * Deduplicate raw notification records into logical Notification records.
     *
     * Creates unified notification records from raw Polaris exports, grouping by:
     * - patron_id + delivery_option_id + notification_type_id (per day)
     *
     * ALL notifications (Email, Mail, Voice, SMS) are created as "pending" during deduplication.
     * The reconciliation process then UPDATES their status based on delivery confirmations:
     *
     * - Email/Mail (1,2): Status updated when NotificationLog/Summary Reports are processed
     * - Voice/SMS (3,8): Status updated when Shoutbomb failure reports are processed (or success-by-absence)
     *
     * Export file data (patron names, item titles, barcodes, etc.) is stored in raw_payload
     * for all notification types, providing enrichment data for the UI.
     *
     * @param \Illuminate\Support\Collection $rawRecords collection of PolarisHoldNotification or PolarisRenewalNotification
     * @param string                         $type       'hold' or 'renewal' for logging
     *
     * @see reconcile() Where notification status is updated based on delivery confirmations
     */
    protected function deduplicateNotifications($rawRecords, string $type): void
    {
        // Group raw rows into logical notifications.
        //
        // - Same patron
        // - Same delivery option
        // - Same notification type
        // → one logical notification for the date window.
        $groups = $rawRecords->groupBy(function ($record) {
            return implode(':', [
                $record->patron_id            ?? '0',
                $record->delivery_option_id   ?? '0',
                $record->notification_type_id ?? '0',
            ]);
        });

        DB::transaction(function () use ($groups) {
            foreach ($groups as $key => $group) {
                /** @var PolarisHoldNotification|PolarisRenewalNotification $first */
                $first = $group->first();

                // Compute timestamps based on raw data.
                $generatedAt = $group
                    ->pluck('notification_creation_date')
                    ->filter()
                    ->map(fn ($v) => Carbon::parse($v))
                    ->min()
                ;

                $firstExportAt = $group
                    ->pluck('export_datetime')
                    ->filter()
                    ->map(fn ($v) => Carbon::parse($v))
                    ->min()
                ;

                $lastExportAt = $group
                    ->pluck('export_datetime')
                    ->filter()
                    ->map(fn ($v) => Carbon::parse($v))
                    ->max()
                ;

                // Capture the full raw Polaris rows for this logical notification so
                // the UI can display comparable per-row details (item, title, site, etc.).
                $rawPayload = $group
                    ->map(static function ($record) {
                        return $record->toArray();
                    })
                    ->values()
                    ->all()
                ;

                // Derive a stable notification_key so later passes can upsert.
                $notificationKey = sha1($key);

                // Get the FIRST export filename (earliest by source_line_number or export_datetime)
                // This is critical for Voice/SMS because the 24-hour grace period clock
                // starts from the datetime in this filename (configured timezone).
                $exportFilename = $group
                    ->sortBy('export_datetime')
                    ->pluck('source_filename')
                    ->filter()
                    ->first()
                ;

                // Sanitize raw payload to ensure valid UTF-8 before JSON encoding.
                // Some Polaris exports contain Windows-1252 or ISO-8859-1 characters
                // (e.g., "Pokémon" encoded as Windows-1252) which cause json_encode to fail.
                $rawPayload = $this->sanitizeUtf8($rawPayload);
                $searchData = $this->buildNotificationSearchColumns($rawPayload);

                // Optionally resolve a human-readable notification type from lookup table.
                $notificationTypeDescription = null;
                $notificationTypeId          = $first->notification_type_id;
                $scheduleStep                = null;

                // For Voice/SMS (3, 8) OVERDUE notifications, calculate the correct
                // notification type based on Shoutbomb's escalation schedule.
                // Polaris always sends notification_type_id = 1 for all overdues,
                // but Shoutbomb escalates based on days since due date.
                $deliveryOptionId = (int) ($first->delivery_option_id ?? 0);
                $isOverdueType    = in_array((int) ($first->notification_type_id ?? 0), [1, 11, 12, 13], true);
                $isVoiceSms       = in_array($deliveryOptionId, [3, 8], true);

                if ($isVoiceSms && $isOverdueType) {
                    // Try to get due_date from raw payload rows
                    $dueDate = $group
                        ->pluck('due_date')
                        ->filter()
                        ->first()
                    ;

                    if ($dueDate) {
                        $escalation = $this->calculateShoutbombOverdueSchedule($dueDate, $firstExportAt);

                        if ($escalation) {
                            $notificationTypeId          = $escalation['notification_type_id'];
                            $notificationTypeDescription = $escalation['notification_type'];
                            $scheduleStep                = $escalation['schedule_step'];
                        }
                    }
                }

                // Fallback: use lookup table if escalation wasn't calculated
                if (null === $notificationTypeDescription && null !== $notificationTypeId) {
                    $typeModel                   = NotificationType::find($notificationTypeId);
                    $notificationTypeDescription = $typeModel?->description;
                }

                // NOTE: Email notifications (delivery_option_id = 2) ARE created here as "pending"
                // even though NotificationLog is the source of truth for their final status.
                //
                // WHY: The reconciliation process UPDATES notifications, it doesn't CREATE them.
                // So we need the notification record to exist first (created here during deduplication),
                // then reconciliation immediately updates the status when NotificationLog data is found.
                //
                // Flow:
                // 1. Deduplication creates email notification with status="pending"
                // 2. Reconciliation runs immediately after and finds NotificationLog entry
                // 3. Status updated to "delivered" or "failed" based on NotificationLog
                // 4. Export file data (patron names, titles, etc.) is already in raw_payload from step 1
                //
                // This is different from the "skip email notifications from export files" approach
                // which was attempted but failed because reconciliation doesn't create notifications.

                // Determine status based on delivery_option_id
                // If delivery_option_id is NULL, the notification cannot be delivered
                $status        = 'pending';
                $failureReason = null;
                $failedAt      = null;

                if (null === $first->delivery_option_id) {
                    $status        = 'failed';
                    $failureReason = 'No delivery method specified';
                    $failedAt      = $firstExportAt ?? now();
                }

                $notification = Notification::updateOrCreate(
                    ['notification_key' => $notificationKey],
                    [
                        'patron_id'            => $first->patron_id,
                        'patron_barcode'       => $first->patron_barcode,
                        'organization_id'      => $first->pickup_organization_id,
                        'delivery_option_id'   => $first->delivery_option_id,
                        'notification_type_id' => $notificationTypeId, // Calculated for Voice/SMS overdues
                        'notification_type'    => $notificationTypeDescription,
                        'schedule_step'        => $scheduleStep, // Shoutbomb escalation step for Voice/SMS
                        'generated_at'         => $generatedAt,
                        'first_exported_at'    => $firstExportAt,
                        'last_exported_at'     => $lastExportAt,
                        'export_filename'      => $exportFilename, // For Voice/SMS grace period clock
                        'status'               => $status,
                        'is_combined'          => $group->count() > 1,
                        'failure_reason'       => $failureReason,
                        'failed_at'            => $failedAt,
                        'raw_payload'          => json_encode($rawPayload),
                        // Display columns: original case for UI
                        'display_patron_first_name' => $searchData['display_patron_first_name'],
                        'display_patron_last_name'  => $searchData['display_patron_last_name'],
                        'display_title'             => $searchData['display_title'],
                        'display_item_barcode'      => $searchData['display_item_barcode'],
                        // Search columns: normalized for matching
                        'search_patron_first_name' => $searchData['search_patron_first_name'],
                        'search_patron_last_name'  => $searchData['search_patron_last_name'],
                        'search_full_name'         => $searchData['search_full_name'],
                        'search_title'             => $searchData['search_title'],
                        'search_item_barcode'      => $searchData['search_item_barcode'],
                        'indexed_patron_phone'     => $searchData['indexed_patron_phone'],
                        'indexed_patron_email'     => $searchData['indexed_patron_email'],
                    ]
                );

                // Activity log: creation or update from export file
                // Prefer the concrete filename here so the UI can show a
                // user-friendly reference like "holds_2026-01-07_09-05-01.txt"
                // instead of internal JSON snapshots.
                $this->activityLogger->log(
                    $notification,
                    source: 'import',
                    description: sprintf(
                        'Notification %s from export file %s',
                        $notification->wasRecentlyCreated ? 'created' : 'updated',
                        $exportFilename ?? '(unknown file)'
                    ),
                    occurredAt: $generatedAt ?? $firstExportAt ?? now(),
                    meta: [
                        'dedupe_key'        => $notificationKey,
                        'file'              => $exportFilename,
                        'group_size'        => $group->count(),
                        'notification_type' => $notificationTypeDescription,
                    ],
                );
            }
        });
    }

    /**
     * Attempt to enrich NotificationLog-derived email/mail notifications with
     * item-level metadata (title, item_barcode, item_record_id) from the
     * original Polaris FTP exports (renewal/overdue tables).
     *
     * This is best-effort and only affects the raw_payload used by the UI;
     * it does not change delivery status logic.
     *
     * @param DeliveryConfirmation             $first
     * @param array<int, array<string, mixed>> $rawPayload
     *
     * @return null|array<int, array<string, mixed>>
     */
    protected function enrichEmailNotificationPayloadFromExports($first, array $rawPayload): ?array
    {
        // Only applies to email/mail notifications with a patron and type
        if (!$first->patron_id || !$first->notification_type_id) {
            return null;
        }

        // Heuristic: use notice_date as the anchor for export matching.
        $noticeDate = $first->notice_date ? Carbon::parse($first->notice_date)->toDateString() : null;

        // Try renewal exports first (auto-renew / almost overdue reminders).
        $renewMatches = PolarisRenewalNotification::query()
            ->where('patron_id', $first->patron_id)
            ->when($first->delivery_option_id, fn ($q) => $q->where('delivery_option_id', $first->delivery_option_id))
            ->when($first->notification_type_id, fn ($q) => $q->where('notification_type_id', $first->notification_type_id))
            ->when($noticeDate, fn ($q) => $q->whereDate('notification_creation_date', $noticeDate))
            ->orderByDesc('notification_creation_date')
            ->limit(5)
            ->get()
        ;

        $overdueMatches = collect();

        if ($renewMatches->isEmpty()) {
            $overdueMatches = PolarisOverdueNotification::query()
                ->where('patron_id', $first->patron_id)
                ->when($first->delivery_option_id, fn ($q) => $q->where('delivery_option_id', $first->delivery_option_id))
                ->when($first->notification_type_id, fn ($q) => $q->where('notification_type_id', $first->notification_type_id))
                ->when($noticeDate, fn ($q) => $q->whereDate('notification_creation_date', $noticeDate))
                ->orderByDesc('notification_creation_date')
                ->limit(5)
                ->get()
            ;
        }

        $sourceRows = $renewMatches->isNotEmpty() ? $renewMatches : $overdueMatches;
        if ($sourceRows->isEmpty()) {
            return null;
        }

        $firstSource = $sourceRows->first();

        $title       = $firstSource->title          ?? null;
        $itemBarcode = $firstSource->item_barcode   ?? null;
        $itemId      = $firstSource->item_record_id ?? null;

        if (!$title && !$itemBarcode && !$itemId) {
            return null; // nothing useful to add
        }

        // Merge into each rawPayload row (non-destructive: only fill missing keys)
        foreach ($rawPayload as &$row) {
            if (!is_array($row)) {
                continue;
            }

            if ($title && empty($row['title'])) {
                $row['title'] = $title;
            }

            if ($itemBarcode && empty($row['item_barcode'])) {
                $row['item_barcode'] = $itemBarcode;
            }

            if ($itemId && empty($row['item_record_id'])) {
                $row['item_record_id'] = $itemId;
            }
        }

        return $rawPayload;
    }

    /**
     * Create Email/Mail notifications from NotificationLog entries.
     *
     * Email (2) and Mail (1) notifications are ONLY created from NotificationLog.
     * Each calendar day of NotificationLog activity is its own combined notification
     * (patron + delivery option + type + DATE(notice_date)). That matches how
     * reconcile matches confirmations and keeps first_exported_at inside the
     * reconcile window instead of collapsing a patron's entire history into one row.
     *
     * New notifications start as 'pending'. Rebuilds must NOT reset status,
     * failure_reason, failed_at, or delivered_at — reconcile owns those fields.
     *
     * @param string $dateString Date to process (Y-m-d format)
     */
    protected function createEmailMailNotificationsFromLog(string $dateString): void
    {
        // NOTE: Entries with empty delivery_string are Polaris data errors (no email address).
        // Per AGENTS.md: "If missing, that is a Polaris data error."
        // These are still tracked as notifications but will be marked as failed.
        $windowStart = Carbon::parse($dateString)->subDays(self::EMAIL_LOG_REBUILD_LOOKBACK_DAYS)->startOfDay();
        $windowEnd   = Carbon::parse($dateString)->addDay()->endOfDay();

        $this->rebuildEmailMailNotificationGroups(
            DeliveryConfirmation::query()
                ->where('confirmation_source', 'notification_log')
                ->whereIn('delivery_option_id', [1, 2])
                ->whereBetween('notice_date', [$windowStart, $windowEnd])
                ->get()
        );
    }

    // No mapping helper is needed here: we preserve Polaris DeliveryOptionID values
    // directly in delivery_option_id and use the delivery_options lookup table
    // for human-readable labels when needed.

    /**
     * Flag delivery issues for patrons.
     *
     * Detects and flags:
     * 1. Notifications with NULL delivery_option_id
     * 2. Failed delivery strings (from Shoutbomb failures)
     * 3. All patrons affected by failed delivery strings
     * 4. LX Starter upload failures (notifications that never reached the SaaS)
     *
     * @param \DateTimeInterface             $date          date being reconciled
     * @param \Illuminate\Support\Collection $notifications notifications for this date
     * @param \Illuminate\Support\Collection $voiceFailures Shoutbomb failure confirmations
     */
    protected function flagDeliveryIssues(\DateTimeInterface $date, $notifications, $voiceFailures): void
    {
        // 1) Flag notifications with NULL delivery preferences
        $this->flagNullDeliveryPreferences($notifications);

        // 2) Flag failed delivery strings and affected patrons
        $this->flagFailedDeliveryStrings($date, $voiceFailures);

        // 2b) Flag Email failures using NotificationLog semantics:
        // - Failed Contact (invalid address): status 13
        // - Failed Delivery (transient): status 14 (escalate to Failed Contact after 3 failures)
        // - Polaris/LX Starter error: empty/NULL delivery_string (always system-level issue)
        $this->flagEmailFailuresFromNotificationLog($date);

        // 3) Flag mismatched preferences
        $this->flagMismatchedPreferences($date);

        // 4) Flag LX Starter upload failures
        $this->flagLxStarterFailures($notifications);
    }

    /**
     * Flag patrons with NULL delivery_option_id.
     *
     * These patrons have no notification preference configured in Polaris
     * and cannot receive notifications.
     *
     * @param \Illuminate\Support\Collection $notifications notifications to check
     */
    protected function flagNullDeliveryPreferences($notifications): void
    {
        foreach ($notifications as $notification) {
            // Skip if delivery option is set
            if (null !== $notification->delivery_option_id) {
                continue;
            }

            // Check if we've already flagged this patron for null preference
            $existing = PatronDeliveryIssue::where('issue_type', 'null_preference')
                ->where('patron_id', $notification->patron_id)
                ->whereNull('resolved_at')
                ->first()
            ;

            if ($existing) {
                continue; // Already flagged
            }

            // Create new issue
            PatronDeliveryIssue::create([
                'issue_type'      => 'null_preference',
                'patron_id'       => $notification->patron_id,
                'patron_barcode'  => $notification->patron_barcode,
                'notification_id' => $notification->id,
                'detected_at'     => now(),
            ]);
        }
    }

    /**
     * Flag failed delivery strings and all patrons using them.
     *
     * When a delivery string (email/phone) fails, create or update a
     * FailedDeliveryString record and flag all patrons using that string.
     *
     * @param \DateTimeInterface             $date          date being reconciled
     * @param \Illuminate\Support\Collection $voiceFailures Shoutbomb failure confirmations
     */
    protected function flagFailedDeliveryStrings(\DateTimeInterface $date, $voiceFailures): void
    {
        // Group failures by delivery string to avoid duplicate counting
        // Each unique delivery_string should only increment failure_count once per date
        $uniqueFailures = $voiceFailures->unique(function ($failure) {
            return $failure->delivery_option_id . ':' . $failure->delivery_string . ':' . ($failure->notice_date ?? '');
        });

        foreach ($uniqueFailures as $failure) {
            $deliveryString = $failure->delivery_string;
            $deliveryOption = $failure->delivery_option_id;

            // Skip if no delivery string (can't identify what failed)
            if (empty($deliveryString) || empty($deliveryOption)) {
                continue;
            }

            // Determine failure source from confirmation_source
            $failureSource  = $failure->confirmation_source; // e.g., 'shoutbomb_invalid_phone'
            $failureDetails = $failure->details;

            // Create or update FailedDeliveryString
            // Use firstOrNew + save pattern to support DB::raw() expressions
            $failedString = FailedDeliveryString::firstOrNew([
                'delivery_option_id' => $deliveryOption,
                'delivery_string'    => $deliveryString,
            ]);

            $failedString->failure_source  = $failureSource;
            $failedString->failure_details = $failureDetails;

            // Use the actual failure date from the delivery confirmation
            $failureDate = $failure->notice_date ? new \DateTime($failure->notice_date) : now();

            // Set first_failed_at only if it's a new record
            if (!$failedString->exists) {
                $failedString->first_failed_at = $failureDate;
                $failedString->failure_count   = 1;
            } else {
                // Only increment if this is a NEW failure (different date than last_failed_at)
                $lastFailedDate     = $failedString->last_failed_at ? $failedString->last_failed_at->format('Y-m-d') : null;
                $currentFailureDate = $failureDate->format('Y-m-d');

                if ($lastFailedDate !== $currentFailureDate) {
                    ++$failedString->failure_count;
                }
            }

            $failedString->last_failed_at = $failureDate;
            $failedString->is_resolved    = false;
            $failedString->save();

            // Find all patrons using this failed delivery string
            $this->flagAffectedPatrons($failedString, $date);
        }
    }

    /**
     * Email failure classification from NotificationLog delivery confirmations.
     *
     * Rules (per docs / operational policy):
     * - Failed Contact: Email NotificationStatusID 13 ("Email Failed - Invalid address")
     * - Failed Delivery (transient): Email NotificationStatusID 14 ("Email Failed")
     *   - Escalate to Failed Contact when the same patron + same email reaches 3 failures
     * - Polaris/LX Starter errors: delivery_string is empty/NULL in NotificationLog
     *   - Always treated as a system-level issue (not a failed contact)
     *
     * This method does not care about notification type or time windows.
     */
    protected function flagEmailFailuresFromNotificationLog(\DateTimeInterface $date): void
    {
        // 1) System-level failures: any NotificationLog email row with empty delivery_string.
        $blank = DeliveryConfirmation::query()
            ->where('confirmation_source', 'notification_log')
            ->where('delivery_option_id', 2)
            ->where(function ($q) {
                $q->whereNull('delivery_string')
                    ->orWhere('delivery_string', '=', '')
                    ->orWhere('delivery_string', '=', 'NULL')
                ;
            })
            // Keep this narrow to "meaningful" Polaris statuses we care about.
            ->whereIn('notification_status_id', [12, 13, 14, 16])
            ->get(['id', 'patron_id', 'patron_barcode', 'notice_date', 'notification_status_id', 'details', 'source_filename', 'source_record_id'])
        ;

        foreach ($blank as $row) {
            // We need a patron identity to create an actionable issue.
            $patronBarcode = $row->patron_barcode ?: null;
            $patronId      = $row->patron_id ?: null;

            if (!$patronBarcode && !$patronId) {
                continue;
            }

            $detectedAt = $row->notice_date ? Carbon::parse($row->notice_date) : now();

            // Dedupe: one open issue per patron+source_record_id (or patron+minute if no id).
            $dedupeKey = $row->source_record_id ?: ($detectedAt->format('Y-m-d H:i') . '|status:' . (string) $row->notification_status_id);

            $existing = PatronDeliveryIssue::query()
                ->where('issue_type', 'system_error')
                ->whereNull('resolved_at')
                ->when($patronBarcode, fn ($q) => $q->where('patron_barcode', $patronBarcode))
                ->when(!$patronBarcode && $patronId, fn ($q) => $q->where('patron_id', $patronId))
                ->where('issue_details', 'like', '%' . $dedupeKey . '%')
                ->first()
            ;

            if ($existing) {
                continue;
            }

            $statusId = (int) ($row->notification_status_id ?? 0);
            $detail   = $row->details ?: 'NotificationLog email row has empty delivery_string';

            PatronDeliveryIssue::create([
                // We use system_error so it appears in the Issues view without adding a new tab/type.
                'issue_type'      => 'system_error',
                'patron_id'       => $patronId,
                'patron_barcode'  => $patronBarcode,
                'notification_id' => null,
                'issue_details'   => trim(sprintf(
                    'Polaris/LX Starter error (NotificationLog): empty delivery_string for Email. status_id=%d; key=%s; file=%s; source_record_id=%s; details=%s',
                    $statusId,
                    $dedupeKey,
                    (string) ($row->source_filename ?? ''),
                    (string) ($row->source_record_id ?? ''),
                    (string) $detail
                )),
                'detected_at' => $detectedAt,
            ]);
        }

        // 2) Failed contacts (status 13) and transient failures (status 14).
        $failures = DeliveryConfirmation::query()
            ->where('confirmation_source', 'notification_log')
            ->where('delivery_option_id', 2)
            ->whereIn('notification_status_id', [13, 14])
            ->whereNotNull('delivery_string')
            ->where('delivery_string', '!=', '')
            ->where('delivery_string', '!=', 'NULL')
            ->get(['patron_barcode', 'delivery_string', 'notice_date', 'notification_status_id', 'details'])
        ;

        foreach ($failures as $row) {
            $email = $this->normalizeEmailDeliveryString((string) $row->delivery_string);
            if ('' === $email) {
                continue;
            }

            $statusId  = (int) ($row->notification_status_id ?? 0);
            $failureAt = $row->notice_date ? new \DateTime($row->notice_date) : now();
            $details   = $row->details ?: null;

            // Failed Contact: invalid email (status 13) → immediate.
            if (13 === $statusId) {
                $this->upsertEmailFailedDeliveryString(
                    $email,
                    failureSource: 'email_invalid_address',
                    failureDetails: $details ?: 'Email Failed - Invalid address',
                    failureAt: $failureAt,
                    desiredFailureCount: null
                );

                continue;
            }

            // Failed Delivery: status 14. Escalate when >= 3 failures for same patron+email.
            if (14 === $statusId) {
                $barcode = $row->patron_barcode ?: null;
                if (!$barcode) {
                    continue;
                }

                [$count, $first, $last] = $this->countEmailFailuresForPatron($barcode, $email);

                if ($count >= 3) {
                    $this->upsertEmailFailedDeliveryString(
                        $email,
                        failureSource: 'email_delivery_failure',
                        failureDetails: $details ?: 'Email delivery failed (3+ failures)',
                        failureAt: $last ?: $failureAt,
                        desiredFailureCount: $count,
                        firstFailureAt: $first
                    );
                }
            }
        }
    }

    /**
     * Normalize an email delivery string into a single email address.
     *
     * - Lowercase
     * - Split on common separators (; , whitespace) and take the first token
     * - Trim trailing punctuation
     */
    protected function normalizeEmailDeliveryString(string $raw): string
    {
        $raw = trim($raw);
        if ('' === $raw || 0 === strcasecmp($raw, 'NULL')) {
            return '';
        }

        $raw       = strtolower($raw);
        $parts     = preg_split('/[;,\s]+/', $raw) ?: [];
        $candidate = $parts[0] ?? $raw;

        return rtrim($candidate, ";,.\" \t\r\n");
    }

    /**
     * Count status-14 email failures for a specific patron+email, across all time.
     *
     * Returns [count, firstDateTime|null, lastDateTime|null].
     */
    protected function countEmailFailuresForPatron(string $patronBarcode, string $normalizedEmail): array
    {
        $rows = DeliveryConfirmation::query()
            ->where('confirmation_source', 'notification_log')
            ->where('delivery_option_id', 2)
            ->where('notification_status_id', 14)
            ->where('patron_barcode', $patronBarcode)
            ->whereNotNull('delivery_string')
            ->where('delivery_string', '!=', '')
            ->where('delivery_string', '!=', 'NULL')
            ->get(['delivery_string', 'notice_date'])
        ;

        $count = 0;
        $first = null;
        $last  = null;

        foreach ($rows as $row) {
            $email = $this->normalizeEmailDeliveryString((string) $row->delivery_string);
            if ('' === $email || $email !== $normalizedEmail) {
                continue;
            }

            ++$count;
            if ($row->notice_date) {
                $dt = new \DateTime((string) $row->notice_date);
                if (!$first || $dt < $first) {
                    $first = $dt;
                }
                if (!$last || $dt > $last) {
                    $last = $dt;
                }
            }
        }

        return [$count, $first, $last];
    }

    /**
     * Create/update a FailedDeliveryString for an email address and flag affected patrons.
     *
     * When $desiredFailureCount is provided, we will set failure_count to at least that value.
     */
    protected function upsertEmailFailedDeliveryString(
        string $email,
        string $failureSource,
        ?string $failureDetails,
        \DateTimeInterface $failureAt,
        ?int $desiredFailureCount = null,
        ?\DateTimeInterface $firstFailureAt = null
    ): void {
        $failedString = FailedDeliveryString::firstOrNew([
            'delivery_option_id' => 2,
            'delivery_string'    => $email,
        ]);

        $failedString->failure_source  = $failureSource;
        $failedString->failure_details = $failureDetails;

        if (!$failedString->exists) {
            $failedString->first_failed_at = $firstFailureAt ?: $failureAt;
            $failedString->failure_count   = $desiredFailureCount ?: 1;
        } else {
            // Keep earliest first_failed_at
            if ($firstFailureAt && $failedString->first_failed_at && $firstFailureAt < $failedString->first_failed_at) {
                $failedString->first_failed_at = $firstFailureAt;
            } elseif ($firstFailureAt && !$failedString->first_failed_at) {
                $failedString->first_failed_at = $firstFailureAt;
            }

            if (null !== $desiredFailureCount) {
                $failedString->failure_count = max((int) ($failedString->failure_count ?? 0), $desiredFailureCount);
            } else {
                // Default behavior: increment once per day (mirrors Voice/SMS behavior).
                $lastFailedDate     = $failedString->last_failed_at ? $failedString->last_failed_at->format('Y-m-d') : null;
                $currentFailureDate = $failureAt->format('Y-m-d');
                if ($lastFailedDate !== $currentFailureDate) {
                    ++$failedString->failure_count;
                }
            }
        }

        $failedString->last_failed_at = $failureAt;
        $failedString->is_resolved    = false;
        $failedString->save();

        // flagAffectedPatrons expects a DateTimeInterface; the exact day is not used
        // for the email-address lookups, so using the failure timestamp is fine.
        $this->flagAffectedPatrons($failedString, $failureAt);
    }

    /**
     * Flag all patrons using a failed delivery string.
     *
     * Searches across hold and renewal notifications to find all patrons
     * using the failed email/phone number.
     *
     * @param FailedDeliveryString $failedString the failed delivery string
     * @param \DateTimeInterface   $date         date being reconciled
     */
    protected function flagAffectedPatrons(FailedDeliveryString $failedString, \DateTimeInterface $date): void
    {
        $deliveryString = $failedString->delivery_string;
        $deliveryOption = $failedString->delivery_option_id;

        $affectedPatrons = collect();

        // Search in hold notifications
        if (3 == $deliveryOption || 8 == $deliveryOption) {
            // Voice/SMS - search patron_phone
            $holdPatrons = PolarisHoldNotification::where('patron_phone', $deliveryString)
                ->select('patron_id', 'patron_barcode')
                ->distinct()
                ->get()
            ;
            $affectedPatrons = $affectedPatrons->merge($holdPatrons);
        } elseif (2 == $deliveryOption) {
            // Email - search patron_email
            $holdPatrons = PolarisHoldNotification::where('patron_email', $deliveryString)
                ->select('patron_id', 'patron_barcode')
                ->distinct()
                ->get()
            ;
            $affectedPatrons = $affectedPatrons->merge($holdPatrons);
        }

        // Search in renewal notifications
        if (3 == $deliveryOption || 8 == $deliveryOption) {
            // Voice/SMS - search patron_phone
            $renewalPatrons = PolarisRenewalNotification::where('patron_phone', $deliveryString)
                ->select('patron_id', 'patron_barcode')
                ->distinct()
                ->get()
            ;
            $affectedPatrons = $affectedPatrons->merge($renewalPatrons);
        } elseif (2 == $deliveryOption) {
            // Email - search patron_email
            $renewalPatrons = PolarisRenewalNotification::where('patron_email', $deliveryString)
                ->select('patron_id', 'patron_barcode')
                ->distinct()
                ->get()
            ;
            $affectedPatrons = $affectedPatrons->merge($renewalPatrons);
        }

        // Search in overdue notifications
        if (3 == $deliveryOption || 8 == $deliveryOption) {
            // Voice/SMS - search patron_phone
            $overduePatrons = PolarisOverdueNotification::where('patron_phone', $deliveryString)
                ->select('patron_id', 'patron_barcode')
                ->distinct()
                ->get()
            ;
            $affectedPatrons = $affectedPatrons->merge($overduePatrons);
        } elseif (2 == $deliveryOption) {
            // Email - search patron_email
            $overduePatrons = PolarisOverdueNotification::where('patron_email', $deliveryString)
                ->select('patron_id', 'patron_barcode')
                ->distinct()
                ->get()
            ;
            $affectedPatrons = $affectedPatrons->merge($overduePatrons);
        }

        // Deduplicate by patron_id
        $affectedPatrons = $affectedPatrons->unique('patron_id');

        // Update affected patron count
        $failedString->update([
            'affected_patron_count' => $affectedPatrons->count(),
        ]);

        // Build a quick lookup of currently affected patron IDs for this
        // delivery string. If a patron previously had a failed contact
        // issue for this string but no longer appears in the affected
        // set, we can safely auto-resolve that issue because Polaris is
        // now reporting a different email/phone for that patron.
        $currentPatronIds = $affectedPatrons
            ->pluck('patron_id')
            ->filter()
            ->unique()
            ->values()
            ->all()
        ;

        // Auto-resolve any existing failed_delivery_string issues where
        // the patron no longer uses this delivery string.
        $existingIssues = PatronDeliveryIssue::where('issue_type', 'failed_delivery_string')
            ->where('failed_delivery_string_id', $failedString->id)
            ->whereNull('resolved_at')
            ->get()
        ;

        foreach ($existingIssues as $issue) {
            if (!in_array($issue->patron_id, $currentPatronIds, true)) {
                $issue->update([
                    'resolved_at'      => now(),
                    'resolution_notes' => 'Auto-resolved: patron contact changed or no longer uses this delivery string',
                ]);
            }
        }

        // Create PatronDeliveryIssue for each currently affected patron
        foreach ($affectedPatrons as $patron) {
            // Check if already flagged for this failed string
            $existing = PatronDeliveryIssue::where('issue_type', 'failed_delivery_string')
                ->where('patron_id', $patron->patron_id)
                ->where('failed_delivery_string_id', $failedString->id)
                ->whereNull('resolved_at')
                ->first()
            ;

            if ($existing) {
                continue; // Already flagged
            }

            // Create new issue
            PatronDeliveryIssue::create([
                'issue_type'                => 'failed_delivery_string',
                'patron_id'                 => $patron->patron_id,
                'patron_barcode'            => $patron->patron_barcode,
                'failed_delivery_string_id' => $failedString->id,
                'detected_at'               => now(),
            ]);
        }
    }

    /**
     * Flag patrons with mismatched delivery preferences.
     *
     * When multiple barcodes share the same phone number but have different
     * delivery preferences (e.g., one set to Voice, another to Text),
     * Shoutbomb cannot deliver notifications. The OLD barcodes need to be
     * updated to match the NEW barcodes' preference.
     *
     * @param \DateTimeInterface $date date being reconciled
     */
    protected function flagMismatchedPreferences(\DateTimeInterface $date): void
    {
        // Get mismatched preference confirmations from delivery_confirmations
        $mismatches = DeliveryConfirmation::query()
            ->where('confirmation_source', 'shoutbomb_mismatched_preference')
            ->whereDate('notice_date', $date->format('Y-m-d'))
            ->get()
            ->groupBy('delivery_string') // Group by phone number
        ;

        foreach ($mismatches as $phone => $phoneMismatches) {
            // Get parsed data from the ingested email
            $firstMismatch = $phoneMismatches->first();
            $ingestedEmail = $firstMismatch->ingestedEmail ?? null;

            if (!$ingestedEmail) {
                continue;
            }

            $parsedData = $ingestedEmail->parsed_data ?? [];

            // Find the mismatch entry for this phone
            $mismatchEntry = collect($parsedData['mismatches'] ?? [])
                ->firstWhere('patron_phone', $phone)
            ;

            if (!$mismatchEntry) {
                continue;
            }

            // Create issues for EACH OLD barcode (the ones that need updating)
            foreach ($mismatchEntry['old_barcodes'] ?? [] as $oldBarcode) {
                // Try to find patron_id for this barcode (optional - patron_id is nullable)
                $patronId = null;
                $patron   = PolarisHoldNotification::where('patron_barcode', $oldBarcode)
                    ->select('patron_id', 'patron_barcode')
                    ->first()
                ;

                if (!$patron) {
                    // Try other tables
                    $patron = PolarisRenewalNotification::where('patron_barcode', $oldBarcode)
                        ->select('patron_id', 'patron_barcode')
                        ->first()
                    ;
                }

                if (!$patron) {
                    $patron = PolarisOverdueNotification::where('patron_barcode', $oldBarcode)
                        ->select('patron_id', 'patron_barcode')
                        ->first()
                    ;
                }

                if ($patron) {
                    $patronId = $patron->patron_id;
                }

                // Check if issue already exists (by barcode, since patron_id might be null)
                $existing = PatronDeliveryIssue::where('issue_type', 'mismatched_preference')
                    ->where('patron_barcode', $oldBarcode)
                    ->whereNull('resolved_at')
                    ->first()
                ;

                if ($existing) {
                    continue; // Already flagged
                }

                // Get delivery option names for display
                $newOptionName = $this->getDeliveryOptionName($mismatchEntry['new_delivery_option_id'] ?? 3);
                $oldOptionName = $this->getDeliveryOptionName($mismatchEntry['old_delivery_option_id'] ?? 8);

                // Create issue for OLD barcode (patron_id is nullable - barcode is the key identifier)
                PatronDeliveryIssue::create([
                    'issue_type'      => 'mismatched_preference',
                    'patron_id'       => $patronId, // May be null if barcode not found in notification tables
                    'patron_barcode'  => $oldBarcode,
                    'notification_id' => null, // Not tied to a specific notification
                    'issue_details'   => sprintf(
                        'Barcode %s is set to %s, but newer barcode(s) %s on phone %s are set to %s. Update this barcode to match the newer preference.',
                        $oldBarcode,
                        $oldOptionName,
                        implode(', ', $mismatchEntry['new_barcodes'] ?? []),
                        $phone,
                        $newOptionName
                    ),
                    'detected_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get human-readable delivery option name using DeliveryOption model.
     * Respects custom labels and enabled status from Display settings.
     */
    protected function getDeliveryOptionName(int $deliveryOptionId): string
    {
        $deliveryOption = DeliveryOption::find($deliveryOptionId);

        if ($deliveryOption) {
            // Return display_label which already respects overrides
            return $deliveryOption->display_label;
        }

        return "Unknown ({$deliveryOptionId})";
    }

    /**
     * Flag electronic notifications that never reached LX Starter SaaS.
     *
     * Identifies electronic delivery notifications (email, SMS, voice) that remain
     * in 'pending' status after reconciliation, indicating a Polaris→LX Starter
     * communication failure. These typically occur during brief time windows
     * (e.g., 8:03-8:06 AM) when the upload connection fails.
     *
     * IMPORTANT: Only flags electronic delivery methods (email/SMS/voice) since
     * LX Starter only handles these delivery types. Mail notifications (delivery_option_id = 1)
     * are excluded because:
     * - They are printed manually by staff
     * - They should remain in 'pending' status until staff processes them
     * - They never go through LX Starter at all
     *
     * Grace Period: Notifications must be pending for at least 48 hours before
     * being flagged as an LX Starter failure. This prevents false positives for
     * notifications that are in normal processing (e.g., notification_log data
     * not yet imported, or delivery confirmations not yet received).
     *
     * Unlike failed_delivery_string issues (where the contact method failed),
     * these are system-level failures where Polaris failed to upload the
     * notification to LX Starter at all.
     *
     * Each notification is flagged individually so staff can investigate whether
     * it's truly an LX Starter failure or another issue.
     *
     * @param \Illuminate\Support\Collection $notifications Notifications to check for LX Starter failures
     */
    protected function flagLxStarterFailures($notifications): void
    {
        $gracePeriodHours = 48;
        $cutoffTime       = now()->subHours($gracePeriodHours);

        // Maximum age for flagging issues - don't flag notifications older than 7 days
        // This prevents flagging old historical data when importing past dates
        $maxAgeDays = 7;
        $maxAgeTime = now()->subDays($maxAgeDays);

        foreach ($notifications as $notification) {
            // Always fetch fresh notification status from database to ensure we have latest state
            // (avoids race conditions or stale Eloquent model data)
            $freshNotification = Notification::find($notification->id);
            if (!$freshNotification) {
                continue; // Notification was deleted
            }

            // Check for existing flag for this PATRON (not this specific notification)
            // We only need one issue per patron, not one per notification
            $existing = PatronDeliveryIssue::where('issue_type', 'lx_starter_failure')
                ->where('patron_id', $freshNotification->patron_id)
                ->whereNull('resolved_at')
                ->first()
            ;

            // If notification is now delivered, resolve any existing flag
            // Note: We only auto-resolve on 'delivered' status, not 'failed'
            // Failed status confirms the lx_starter_failure issue is real and needs attention
            if ('delivered' === $freshNotification->status) {
                if ($existing) {
                    $existing->update([
                        'resolved_at'      => now(),
                        'resolution_notes' => 'Auto-resolved: notification was successfully delivered',
                    ]);
                }

                continue;
            }

            // If notification is failed, keep the issue active - it needs attention
            if ('failed' === $freshNotification->status) {
                continue; // Don't auto-resolve, don't create duplicate
            }

            // Only continue processing if still pending
            if ('pending' !== $freshNotification->status) {
                continue;
            }

            // Only flag notifications that:
            // 1. Are still pending after reconciliation
            // 2. Have a valid delivery option (not null_preference issues)
            // 3. Are EMAIL ONLY - LX Starter ONLY handles email (delivery_option_id = 2)
            //    Voice (3) and SMS (8) use Shoutbomb, not LX Starter - see AGENTS.md
            if (null === $freshNotification->delivery_option_id) {
                continue; // This will be flagged as null_preference instead
            }

            // CRITICAL: LX Starter only handles Email (delivery_option_id = 2)
            // Voice/SMS use Shoutbomb and have their own failure detection via Shoutbomb reports
            if (2 !== $freshNotification->delivery_option_id) {
                continue; // Not email - LX Starter doesn't apply
            }

            // LX Starter failures are notifications that remain pending AFTER reconciliation
            // and should have been uploaded/processed. These notifications will have a
            // first_exported_at timestamp (when they were exported for delivery).
            if (null === $freshNotification->first_exported_at) {
                continue; // No export timestamp - not eligible for this check
            }

            // Only flag notifications that have been pending for at least the grace period
            // This prevents false positives for notifications in normal processing
            if ($freshNotification->first_exported_at > $cutoffTime) {
                continue; // Too recent, still within grace period
            }

            // CRITICAL: Don't flag old historical notifications when importing past data
            // Only flag notifications within the last 7 days to avoid creating thousands
            // of stale issues when importing historical data
            if ($freshNotification->first_exported_at < $maxAgeTime) {
                continue; // Too old - likely historical data that's no longer relevant
            }

            // If already flagged for this patron, just update the notification_id to most recent
            if ($existing) {
                // Update to point to most recent failing notification
                $existing->update([
                    'notification_id' => $freshNotification->id,
                    'detected_at'     => $existing->detected_at, // Keep original detection time
                ]);

                continue;
            }

            // Create new LX Starter failure issue - ONE per patron (not per notification)
            PatronDeliveryIssue::create([
                'issue_type'      => 'lx_starter_failure',
                'patron_id'       => $freshNotification->patron_id,
                'patron_barcode'  => $freshNotification->patron_barcode,
                'notification_id' => $freshNotification->id,
                'detected_at'     => now(),
            ]);
        }
    }

    /**
     * Calculate the Shoutbomb overdue schedule step for Voice/SMS notifications.
     *
     * Shoutbomb escalates overdue notifications based on days since due date:
     * - 1 day after due date → 1st Overdue (notification_type_id = 1)
     * - 7 days after due date → 2nd Overdue (notification_type_id = 12)
     * - 14 days after due date → 3rd Overdue (notification_type_id = 13)
     * - 21 days after due date → Final Bill (notification_type_id = 11)
     * - 42 days after due date → Collection (notification_type_id = 11, but labeled differently)
     *
     * IMPORTANT: Polaris always sends notification_type_id = 1 for all overdues.
     * This application must track the escalation based on timing.
     *
     * @param null|\DateTimeInterface|string $dueDate        Item due date
     * @param null|\DateTimeInterface|string $exportDatetime When the notification was exported
     *
     * @return null|array{notification_type_id: int, notification_type: string, schedule_step: int}
     */
    protected function calculateShoutbombOverdueSchedule($dueDate, $exportDatetime): ?array
    {
        if (!$dueDate || !$exportDatetime) {
            return null;
        }

        try {
            $due = $dueDate instanceof \DateTimeInterface
                ? Carbon::instance($dueDate)
                : Carbon::parse($dueDate);

            $export = $exportDatetime instanceof \DateTimeInterface
                ? Carbon::instance($exportDatetime)
                : Carbon::parse($exportDatetime);
        } catch (\Throwable $e) {
            return null;
        }

        // Calculate days overdue (export date - due date)
        $daysOverdue = $due->startOfDay()->diffInDays($export->startOfDay(), false);

        // Map days overdue to Shoutbomb schedule
        // Using ranges to handle edge cases (e.g., notification sent a day late)
        if ($daysOverdue >= 42) {
            // Collection Agency - 42+ days
            return [
                'notification_type_id' => 11, // Bill (reused for collection)
                'notification_type'    => 'Collection',
                'schedule_step'        => 5,
            ];
        }

        if ($daysOverdue >= 21) {
            // Final Bill - 21-41 days
            return [
                'notification_type_id' => 11,
                'notification_type'    => 'Bill',
                'schedule_step'        => 4,
            ];
        }

        if ($daysOverdue >= 14) {
            // 3rd Overdue - 14-20 days
            return [
                'notification_type_id' => 13,
                'notification_type'    => '3rd Overdue',
                'schedule_step'        => 3,
            ];
        }

        if ($daysOverdue >= 7) {
            // 2nd Overdue - 7-13 days
            return [
                'notification_type_id' => 12,
                'notification_type'    => '2nd Overdue',
                'schedule_step'        => 2,
            ];
        }

        // 1st Overdue - 1-6 days (or less, edge case)
        return [
            'notification_type_id' => 1,
            'notification_type'    => '1st Overdue',
            'schedule_step'        => 1,
        ];
    }

    /**
     * Get the patron's delivery preference from FTP export files.
     *
     * Checks holds, overdues, and renewals exports for the most recent delivery_option_id.
     * Returns null if no FTP export found for this patron.
     *
     * @return null|int delivery_option_id (1=Mail, 2=Email, 3=Voice, 8=SMS) or null
     */
    protected function getPatronFtpDeliveryPreference(string $patronBarcode): ?int
    {
        // Check holds exports (most common)
        $hold = PolarisHoldNotification::query()
            ->where('patron_barcode', $patronBarcode)
            ->whereNotNull('delivery_option_id')
            ->orderByDesc('created_at')
            ->value('delivery_option_id')
        ;

        if (null !== $hold) {
            return (int) $hold;
        }

        // Check overdue exports
        $overdue = PolarisOverdueNotification::query()
            ->where('patron_barcode', $patronBarcode)
            ->whereNotNull('delivery_option_id')
            ->orderByDesc('created_at')
            ->value('delivery_option_id')
        ;

        if (null !== $overdue) {
            return (int) $overdue;
        }

        // Check renewal exports
        $renewal = PolarisRenewalNotification::query()
            ->where('patron_barcode', $patronBarcode)
            ->whereNotNull('delivery_option_id')
            ->orderByDesc('created_at')
            ->value('delivery_option_id')
        ;

        if (null !== $renewal) {
            return (int) $renewal;
        }

        return null;
    }

    /**
     * Derive display and search columns from raw payload rows.
     *
     * Returns two sets of columns:
     * - display_*: Original case for UI rendering
     * - search_*: Normalized (lowercase, punctuation stripped) for matching
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, null|string>
     *
     * @see SearchNormalizer
     */
    private function buildNotificationSearchColumns(array $rows): array
    {
        // First try standard raw payload extraction (from FTP exports)
        $patronFirstName = RawPayloadExtractor::getPatronFirstName($rows);
        $patronLastName  = RawPayloadExtractor::getPatronLastName($rows);

        // Fallback: if no patron names in standard fields, try parsing from 'details' field
        // This handles NotificationLog entries which store patron info in a details line like:
        // "SAMPLE PATRON 20000409079210 patron3596@example.org"
        if (!$patronFirstName && !$patronLastName) {
            $parsed = $this->parsePatronNameFromDetails($rows);
            if ($parsed) {
                $patronFirstName = $parsed['first_name'];
                $patronLastName  = $parsed['last_name'];
            }
        }

        $title       = RawPayloadExtractor::getItemTitle($rows);
        $itemBarcode = RawPayloadExtractor::getItemBarcode($rows);

        // Build full name for combined search ("First Last" format)
        $fullName = trim(($patronFirstName ?? '') . ' ' . ($patronLastName ?? ''));

        return [
            // Display columns: original case for UI
            'display_patron_first_name' => $patronFirstName,
            'display_patron_last_name'  => $patronLastName,
            'display_title'             => $title,
            'display_item_barcode'      => $itemBarcode,
            // Search columns: normalized for matching
            'search_patron_first_name' => SearchNormalizer::normalizeName($patronFirstName),
            'search_patron_last_name'  => SearchNormalizer::normalizeName($patronLastName),
            'search_full_name'         => SearchNormalizer::normalizeName($fullName),
            'search_title'             => SearchNormalizer::normalizeTitle($title),
            'search_item_barcode'      => SearchNormalizer::normalizeBarcode($itemBarcode),
            'indexed_patron_phone'     => SearchNormalizer::normalizePhone(RawPayloadExtractor::getPatronPhone($rows)),
            'indexed_patron_email'     => SearchNormalizer::normalizeEmail(RawPayloadExtractor::getPatronEmail($rows)),
        ];
    }

    /**
     * Parse patron name from the 'details' field found in NotificationLog/summary report data.
     *
     * Details lines typically follow the format:
     * "SAMPLE PATRON 20000409079210 EMAIL@EXAMPLE.COM"
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return null|array{first_name: null|string, last_name: null|string}
     */
    private function parsePatronNameFromDetails(array $rows): ?array
    {
        // Find the first non-empty 'details' field
        $detailsLine   = null;
        $patronBarcode = null;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (empty($detailsLine) && !empty($row['details']) && 'NULL' !== $row['details']) {
                $detailsLine = trim($row['details']);
            }

            if (empty($patronBarcode) && !empty($row['patron_barcode'])) {
                $patronBarcode = $row['patron_barcode'];
            }
        }

        if (!$detailsLine) {
            return null;
        }

        // Parse the details line: "FIRST MIDDLE LAST BARCODE EMAIL"
        $tokens = preg_split('/\s+/', $detailsLine);

        if (!$tokens || count($tokens) < 2) {
            return null;
        }

        // Remove email (token with @)
        $tokens = array_filter($tokens, fn ($t) => !str_contains($t, '@'));
        $tokens = array_values($tokens);

        // Find barcode index - either matches known barcode or is numeric string of 8+ digits
        $barcodeIndex = null;
        foreach ($tokens as $i => $token) {
            if (($patronBarcode && $token === $patronBarcode) || preg_match('/^\d{8,}$/', $token)) {
                $barcodeIndex = $i;

                break;
            }
        }

        // Everything before barcode is the name
        if (null === $barcodeIndex || $barcodeIndex < 1) {
            return null;
        }

        $nameTokens = array_slice($tokens, 0, $barcodeIndex);

        if (empty($nameTokens)) {
            return null;
        }

        // Last token is last name, everything else is first name(s)
        $lastName  = array_pop($nameTokens);
        $firstName = !empty($nameTokens) ? implode(' ', $nameTokens) : null;

        return [
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ];
    }

    /**
     * Recursively sanitize all string values in an array to ensure valid UTF-8.
     *
     * Polaris exports sometimes contain Windows-1252 or ISO-8859-1 encoded characters
     * which cause json_encode() to fail with "Malformed UTF-8" errors.
     *
     * This method attempts to detect and convert non-UTF-8 strings to UTF-8.
     */
    private function sanitizeUtf8(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(fn ($value) => $this->sanitizeUtf8($value), $data);
        }

        if (is_string($data)) {
            // Check if string is valid UTF-8
            if (!mb_check_encoding($data, 'UTF-8')) {
                // Try to convert from common Polaris encodings to UTF-8
                // Windows-1252 is most common, but ISO-8859-1 is also possible
                $converted = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');

                // Verify the conversion worked
                if (mb_check_encoding($converted, 'UTF-8')) {
                    return $converted;
                }

                // Fallback: replace invalid UTF-8 sequences with replacement character
                return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
        }

        return $data;
    }
}
