<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Contracts\NotificationFailureDetectorInterface;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Dcplibrary\Notices\Models\Notification;

/**
 * Detects notification failures and at-risk patrons.
 *
 * This service correlates DeliveryConfirmation records (from email failure
 * reports) with unified Notification records to identify:
 * - Notifications that failed to deliver
 * - Patrons with invalid phone numbers
 * - Notifications that should be marked as successfully delivered (absence of failure)
 */
class NotificationFailureDetector implements NotificationFailureDetectorInterface
{
    public function detectFailures(\DateTimeInterface $date): array
    {
        $dayStart = Carbon::instance($date)->startOfDay();
        $dayEnd   = $dayStart->copy()->endOfDay();

        // Get all delivery confirmations (failure reports) for this date
        $failures = DeliveryConfirmation::whereDate('notice_date', $date)
            ->whereIn('confirmation_source', [
                'shoutbomb_voice_failure',
                'shoutbomb_voice_system_error',
            ])
            ->get()
        ;

        $detected = [];

        foreach ($failures as $confirmation) {
            // Try to match this failure to a notification by patron_barcode and delivery_option_id
            $notification = $this->findMatchingNotification($confirmation, $dayStart, $dayEnd);

            // If no match by barcode, try matching by phone number (for system errors without barcode)
            if (!$notification && $confirmation->delivery_string) {
                $notification = $this->findMatchingNotificationByPhone($confirmation, $dayStart, $dayEnd);
            }

            if ($notification) {
                // Mark the notification as failed
                $notification->update([
                    'status'         => 'failed',
                    'failed_at'      => now(),
                    'delivered_at'   => null,
                    'failure_reason' => $confirmation->details,
                ]);

                // Link the confirmation to the notification
                $confirmation->update([
                    'notification_id'  => $notification->id,
                    'notification_key' => $notification->notification_key,
                ]);

                $detected[] = [
                    'notification_id'    => $notification->id,
                    'patron_barcode'     => $confirmation->patron_barcode ?: $notification->patron_barcode,
                    'delivery_option_id' => $confirmation->delivery_option_id,
                    'failure_reason'     => $confirmation->details,
                    'confirmation_id'    => $confirmation->id,
                    'matched_by'         => $confirmation->patron_barcode ? 'barcode' : 'phone',
                ];
            } else {
                // Couldn't match to a notification - log it for investigation
                $detected[] = [
                    'notification_id'    => null,
                    'patron_barcode'     => $confirmation->patron_barcode,
                    'phone'              => $confirmation->delivery_string,
                    'delivery_option_id' => $confirmation->delivery_option_id,
                    'failure_reason'     => $confirmation->details,
                    'confirmation_id'    => $confirmation->id,
                    'unmatched'          => true,
                ];
            }
        }

        // Mark notifications without failures as successfully delivered
        // (Shoutbomb only reports failures, so absence = success)
        $this->markSuccessfulDeliveries($dayStart, $dayEnd, $failures);

        return $detected;
    }

    public function detectInvalidPhoneNumbers(\DateTimeInterface $date): array
    {
        $dayStart   = Carbon::instance($date)->startOfDay();
        $dayEnd     = $dayStart->copy()->endOfDay();
        $dateString = $dayStart->toDateString();

        // Get invalid phone number confirmations whose notice_date is on or before
        // the target date. This prevents timing bugs where an invalid phone report
        // is imported on a later day than the original export/notice_date.
        //
        // Example:
        // - Export file: renew_2026-01-07_08-03-00.txt  (notice_date = 2026-01-07)
        // - Invalid phone email received: 2026-01-08 (report_date = 2026-01-07)
        //
        // When detectInvalidPhoneNumbers() runs for 2026-01-08, it MUST still
        // consider the 2026-01-07 invalid-phone confirmations so that any
        // notifications that were temporarily marked delivered via
        // success-by-absence can be downgraded to failed once the Shoutbomb
        // failure evidence exists.
        $invalidPhones = DeliveryConfirmation::where('confirmation_source', 'shoutbomb_invalid_phone')
            ->whereDate('notice_date', '<=', $dateString)
            ->get()
        ;

        $detected = [];

        foreach ($invalidPhones as $confirmation) {
            $detected[] = [
                'patron_id'          => $confirmation->patron_id,
                'patron_barcode'     => $confirmation->patron_barcode,
                'phone'              => $confirmation->delivery_string,
                'delivery_option_id' => $confirmation->delivery_option_id,
                'organization_id'    => $confirmation->reporting_org_id,
                'details'            => $confirmation->details,
            ];

            // Find all pending/delivered notifications for this patron with voice/SMS delivery
            // Match by patron_id, patron_barcode, or phone number
            $affectedNotifications = collect();

            if ($confirmation->patron_id) {
                $affectedNotifications = $affectedNotifications->merge(
                    Notification::where('patron_id', $confirmation->patron_id)
                        ->whereIn('delivery_option_id', [3, 8])
                        ->whereIn('status', ['pending', 'delivered'])
                        ->get()
                );
            }

            if ($confirmation->patron_barcode) {
                $affectedNotifications = $affectedNotifications->merge(
                    Notification::where('patron_barcode', $confirmation->patron_barcode)
                        ->whereIn('delivery_option_id', [3, 8])
                        ->whereIn('status', ['pending', 'delivered'])
                        ->get()
                );
            }

            // If no patron info, try to find by phone number
            if ($affectedNotifications->isEmpty() && $confirmation->delivery_string) {
                $phone          = $confirmation->delivery_string;
                $patronBarcodes = \DB::table('polaris_hold_notifications')
                    ->where('patron_phone', $phone)
                    ->distinct()
                    ->pluck('patron_barcode')
                    ->filter()
                    ->toArray()
                ;

                if (!empty($patronBarcodes)) {
                    $affectedNotifications = $affectedNotifications->merge(
                        Notification::whereIn('patron_barcode', $patronBarcodes)
                            ->whereIn('delivery_option_id', [3, 8])
                            ->whereIn('status', ['pending', 'delivered'])
                            ->get()
                    );
                }
            }

            // Remove duplicates
            $affectedNotifications = $affectedNotifications->unique('id');

            foreach ($affectedNotifications as $notification) {
                $notification->update([
                    'status'         => 'failed',
                    'failed_at'      => now(),
                    'failure_reason' => 'Invalid phone number: ' . $confirmation->details,
                ]);

                // Link the confirmation to the notification
                $confirmation->update([
                    'notification_id'  => $notification->id,
                    'notification_key' => $notification->notification_key,
                ]);
            }
        }

        return $detected;
    }

    public function detectEmailFailures(\DateTimeInterface $date): array
    {
        $dayStart = Carbon::instance($date)->startOfDay();
        $dayEnd   = $dayStart->copy()->endOfDay();

        // Get Polaris Summary Report confirmations for this date
        $confirmations = DeliveryConfirmation::whereDate('notice_date', $date)
            ->where('delivery_option_id', 2) // Email only
            ->whereIn('confirmation_source', [
                'polaris_summary_report_success',
                'polaris_summary_report_system_failure',
                'polaris_summary_report_failure',
            ])
            ->get()
        ;

        $detected = [];

        foreach ($confirmations as $confirmation) {
            // Match this confirmation to an email notification by patron_barcode
            $notification = $this->findMatchingEmailNotification($confirmation, $dayStart, $dayEnd);

            if ($notification) {
                // Determine status based on confirmation source
                if ('polaris_summary_report_success' === $confirmation->confirmation_source) {
                    // Successfully delivered
                    $notification->update([
                        'status'       => 'delivered',
                        'delivered_at' => $dayStart->copy()->addHours(2), // Estimate delivery time
                    ]);

                    $detected[] = [
                        'notification_id'    => $notification->id,
                        'patron_barcode'     => $confirmation->patron_barcode,
                        'email'              => $confirmation->delivery_string,
                        'delivery_option_id' => $confirmation->delivery_option_id,
                        'status'             => 'delivered',
                        'confirmation_id'    => $confirmation->id,
                    ];
                } elseif ('polaris_summary_report_system_failure' === $confirmation->confirmation_source) {
                    // Polaris system failure ("Email Failed")
                    $notification->update([
                        'status'         => 'failed',
                        'failed_at'      => now(),
                        'delivered_at'   => null,
                        'failure_reason' => $confirmation->details,
                    ]);

                    $detected[] = [
                        'notification_id'    => $notification->id,
                        'patron_barcode'     => $confirmation->patron_barcode,
                        'email'              => $confirmation->delivery_string,
                        'delivery_option_id' => $confirmation->delivery_option_id,
                        'status'             => 'failed',
                        'failure_reason'     => 'polaris_system_failure',
                        'confirmation_id'    => $confirmation->id,
                    ];
                } else {
                    // Actual failure from "failed to:" section
                    $notification->update([
                        'status'         => 'failed',
                        'failed_at'      => now(),
                        'delivered_at'   => null,
                        'failure_reason' => 'Email delivery failed: ' . $confirmation->details,
                    ]);

                    $detected[] = [
                        'notification_id'    => $notification->id,
                        'patron_barcode'     => $confirmation->patron_barcode,
                        'email'              => $confirmation->delivery_string,
                        'delivery_option_id' => $confirmation->delivery_option_id,
                        'status'             => 'failed',
                        'failure_reason'     => 'email_delivery_failed',
                        'confirmation_id'    => $confirmation->id,
                    ];
                }

                // Link the confirmation to the notification
                $confirmation->update([
                    'notification_id'  => $notification->id,
                    'notification_key' => $notification->notification_key,
                ]);
            } else {
                // Couldn't match to a notification - log it for investigation
                $detected[] = [
                    'notification_id'    => null,
                    'patron_barcode'     => $confirmation->patron_barcode,
                    'email'              => $confirmation->delivery_string,
                    'delivery_option_id' => $confirmation->delivery_option_id,
                    'status'             => 'unmatched',
                    'confirmation_id'    => $confirmation->id,
                    'unmatched'          => true,
                ];
            }
        }

        // Mark emails absent from Summary Report as needing investigation
        // These could be soft bounces or other issues
        $this->markEmailsForInvestigation($dayStart, $dayEnd, $confirmations);

        return $detected;
    }

    /**
     * Find a notification matching the delivery confirmation.
     *
     * Match by patron_barcode, delivery_option_id, and date range.
     */
    protected function findMatchingNotification(DeliveryConfirmation $confirmation, Carbon $dayStart, Carbon $dayEnd): ?Notification
    {
        if (!$confirmation->patron_barcode) {
            return null;
        }

        // Look for notifications exported on or around this date
        // with matching patron and delivery option
        // Include 'delivered' status because failures can be discovered after auto-marking as delivered
        return Notification::where('patron_barcode', $confirmation->patron_barcode)
            ->where('delivery_option_id', $confirmation->delivery_option_id)
            ->whereIn('status', ['pending', 'delivered'])
            ->whereBetween('first_exported_at', [$dayStart->copy()->subDay(), $dayEnd->copy()->addDay()])
            ->orderBy('first_exported_at', 'desc')
            ->first()
        ;
    }

    /**
     * Find a notification matching the delivery confirmation by phone number.
     *
     * Used when patron_barcode is not available (e.g., "Calls that generated errors").
     */
    protected function findMatchingNotificationByPhone(DeliveryConfirmation $confirmation, Carbon $dayStart, Carbon $dayEnd): ?Notification
    {
        if (!$confirmation->delivery_string) {
            return null;
        }

        $phone = \Dcplibrary\Notices\Support\SearchNormalizer::normalizePhone($confirmation->delivery_string);

        if (!$phone) {
            return null;
        }

        // Restrict to the reported export cycle, matching NotificationReconciler's
        // phone matching. An undated match would mark an unrelated (often newer)
        // notice failed, which reconciliation then flips back to delivered.
        $window = [$dayStart->copy()->subDay(), $dayEnd->copy()->addDay()];

        $byIndexedPhone = Notification::where('indexed_patron_phone', $phone)
            ->where('delivery_option_id', $confirmation->delivery_option_id ?: 3) // Default to voice
            ->whereIn('status', ['pending', 'delivered'])
            ->whereBetween('first_exported_at', $window)
            ->orderBy('first_exported_at', 'desc')
            ->first()
        ;

        if ($byIndexedPhone) {
            return $byIndexedPhone;
        }

        // Older notifications may predate indexed_patron_phone; fall back to the hold export phone.
        $patronBarcodes = \DB::table('polaris_hold_notifications')
            ->where('patron_phone', $phone)
            ->distinct()
            ->pluck('patron_barcode')
            ->filter()
            ->toArray()
        ;

        if (empty($patronBarcodes)) {
            return null;
        }

        return Notification::whereIn('patron_barcode', $patronBarcodes)
            ->where('delivery_option_id', $confirmation->delivery_option_id ?: 3)
            ->whereIn('status', ['pending', 'delivered'])
            ->whereBetween('first_exported_at', $window)
            ->orderBy('first_exported_at', 'desc')
            ->first()
        ;
    }

    /**
     * Mark notifications as successfully delivered if they don't appear in failure reports.
     *
     * CRITICAL: This uses the notice_date from the export file name, NOT the database import timestamp.
     * Success-by-absence detection works like this:
     *
     * 1. Notifications are exported on date X (export file date = X, stored in first_exported_at)
     * 2. Shoutbomb sends failure reports with notice_date = X (the export date)
     * 3. If a notification exported on date X does NOT appear in failure reports with notice_date = X, mark as delivered
     *
     * This logic is independent of when data is imported into the database - it only cares about
     * the export file date and the failure report notice_date.
     *
     * @param Carbon $dayStart Start of the notice_date being processed (from failure reports)
     * @param Carbon $dayEnd   End of the notice_date being processed
     * @param mixed  $failures Failure reports for this notice_date
     */
    protected function markSuccessfulDeliveries(Carbon $dayStart, Carbon $dayEnd, $failures): void
    {
        // Get all patron barcodes that had failures reported for this notice_date
        $failedBarcodes = $failures->pluck('patron_barcode')->filter()->unique()->toArray();

        // Find voice/SMS notifications exported on THIS DATE
        // The notice_date in failure reports = the export file date
        // Match by comparing DATE(first_exported_at) with notice_date
        $exportDate           = $dayStart->toDateString();
        $pendingNotifications = Notification::whereIn('delivery_option_id', [3, 8]) // Voice and SMS
            ->where('status', 'pending')
            ->whereDate('first_exported_at', $exportDate)
            ->get()
        ;

        foreach ($pendingNotifications as $notification) {
            // Skip if this patron appears in the failure list for this notice_date
            if (in_array($notification->patron_barcode, $failedBarcodes, true)) {
                continue;
            }

            // Check if ANY failure exists for this notification with the same notice_date
            // Match by patron_barcode, delivery_option_id, and notice_date
            $hasFailureRecord = DeliveryConfirmation::where('patron_barcode', $notification->patron_barcode)
                ->where('delivery_option_id', $notification->delivery_option_id)
                ->whereIn('confirmation_source', [
                    'shoutbomb_voice_failure',
                    'shoutbomb_voice_system_error',
                    'shoutbomb_invalid_phone',
                ])
                ->whereDate('notice_date', $dayStart->toDateString())
                ->exists()
            ;

            if ($hasFailureRecord) {
                // Failure exists - skip marking as delivered (will be handled by detectFailures)
                continue;
            }

            // CRITICAL: Voice/SMS require 24-hour grace period + import batch before marking as delivered
            // This aligns with the reconciler logic (NotificationReconciler::reconcile lines 525-597)
            $gracePeriodHours = 24;
            $gracePeriodEnd   = $notification->first_exported_at
                ? Carbon::instance($notification->first_exported_at)->addHours($gracePeriodHours)
                : null;

            $gracePeriodPassed = $gracePeriodEnd && now() >= $gracePeriodEnd;

            // Check if an import batch ran AFTER the 24-hour mark
            $importAfterGracePeriod = $gracePeriodPassed && \Dcplibrary\Notices\Models\NotificationImportBatch::query()
                ->where('date_for', '>=', Carbon::instance($notification->first_exported_at)->format('Y-m-d'))
                ->where('imported_at', '>', $notification->first_exported_at)
                ->where('imported_at', '>', $gracePeriodEnd)
                ->exists()
            ;

            // BOTH conditions must be met before marking as delivered
            if (!$gracePeriodPassed || !$importAfterGracePeriod) {
                // Grace period not passed or no import after grace period yet - leave as pending
                continue;
            }

            // Mark as successfully delivered
            // Use first_exported_at as delivery time (we know it succeeded within the grace period)
            $notification->update([
                'status'       => 'delivered',
                'delivered_at' => $notification->first_exported_at,
            ]);
        }
    }

    /**
     * Find an email notification matching the delivery confirmation.
     *
     * Match by patron_barcode, delivery_option_id (2 = Email), and date range.
     */
    protected function findMatchingEmailNotification(DeliveryConfirmation $confirmation, Carbon $dayStart, Carbon $dayEnd): ?Notification
    {
        if (!$confirmation->patron_barcode) {
            return null;
        }

        // Look for email notifications exported on or around this date
        // Include 'delivered' status because confirmations can arrive after auto-marking
        return Notification::where('patron_barcode', $confirmation->patron_barcode)
            ->where('delivery_option_id', 2) // Email
            ->whereIn('status', ['pending', 'delivered'])
            ->whereBetween('first_exported_at', [$dayStart->copy()->subDay(), $dayEnd->copy()->addDay()])
            ->orderBy('first_exported_at', 'desc')
            ->first()
        ;
    }

    /**
     * Mark email notifications absent from Summary Report as needing investigation.
     *
     * CRITICAL: This uses the notice_date from the Summary Report, NOT the database import timestamp.
     * Email Summary Reports reference the export file date via notice_date.
     *
     * @param Carbon $dayStart      Start of the notice_date being processed (from Summary Report)
     * @param Carbon $dayEnd        End of the notice_date being processed
     * @param mixed  $confirmations Email confirmations from Summary Report for this notice_date
     */
    protected function markEmailsForInvestigation(Carbon $dayStart, Carbon $dayEnd, $confirmations): void
    {
        // Get all patron barcodes that appear in the Summary Report for this notice_date
        $reportedBarcodes = $confirmations->pluck('patron_barcode')->filter()->unique()->toArray();

        // CRITICAL: Find email notifications exported on THIS SAME DATE (notice_date)
        // The notice_date in Summary Reports = the export file date
        // Match by comparing DATE(first_exported_at) with notice_date
        $pendingEmails = Notification::where('delivery_option_id', 2) // Email
            ->where('status', 'pending')
            ->whereDate('first_exported_at', $dayStart->toDateString())
            ->get()
        ;

        foreach ($pendingEmails as $notification) {
            // Skip if this patron appears in the Summary Report for this notice_date
            if (in_array($notification->patron_barcode, $reportedBarcodes, true)) {
                continue;
            }

            // Mark for investigation (absent from Summary Report = possible soft bounce)
            $notification->update([
                'status'         => 'investigation_required',
                'failure_reason' => 'Email notification absent from Summary Report - possible soft bounce or delivery delay',
            ]);
        }
    }
}
