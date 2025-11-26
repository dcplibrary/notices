<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationEvent;
use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Support\Facades\Log;

/**
 * Projects rows from notification_logs (Polaris NotificationLog)
 * into the master notifications + notification_events lifecycle.
 *
 * This treats NotificationLog as the source of truth for:
 * - Mail (DeliveryOptionID = 1)
 * - Email (DeliveryOptionID = 2)
 * - Voice (DeliveryOptionID = 3)
 * - SMS (DeliveryOptionID = 8)
 */
class NotificationProjectionService
{
    /**
     * Project a single NotificationLog row into Notification + NotificationEvent.
     */
    public function syncFromLog(NotificationLog $log): Notification
    {
        // Find or create the master notification by Polaris NotificationLogID
        $notification = Notification::firstOrNew([
            'notification_log_id' => $log->polaris_log_id,
        ]);

        $notification->fill([
            'notification_type_id'   => $log->notification_type_id,
            'notification_level'     => null, // can be enriched from PhoneNotices/exports later
            'patron_barcode'         => $log->patron_barcode,
            'patron_id'              => $log->patron_id,
            'notice_date'            => optional($log->notification_date)?->toDateString(),
            'delivery_option_id'     => $log->delivery_option_id,
            'delivery_string'        => $log->delivery_string,
            'reporting_org_id'       => $log->reporting_org_id,
            // Snapshots from existing accessors on NotificationLog
            'patron_name_first'      => $log->patron_first_name,
            'patron_name_last'       => $log->patron_last_name,
            'patron_email'           => $log->patron_email,
            'patron_phone'           => $log->patron_phone,
        ]);

        $notification->save();

        // Enrich from PhoneNotices + export tables
        $this->enrichFromPhoneNotices($notification);
        $this->enrichFromExportTables($notification);

        // Mirror current NotificationLog status as a lifecycle event
        NotificationEvent::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'source_table'    => 'notification_logs',
                'source_id'       => $log->id,
            ],
            [
                'event_type'         => $this->mapStatusToEventType($log),
                'event_at'           => $log->notification_date,
                'delivery_option_id' => $log->delivery_option_id,
                'status_code'        => (string) $log->notification_status_id,
                'status_text'        => $this->mapStatusText($log),
                'source_file'        => null,
                'import_job_id'      => null,
            ]
        );

        return $notification;
    }

    /**
     * Project all NotificationLog rows in a date range.
     */
    public function syncRange(Carbon $start, Carbon $end): int
    {
        $count = 0;

        NotificationLog::whereBetween('notification_date', [$start, $end])
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$count) {
                foreach ($logs as $log) {
                    try {
                        $this->syncFromLog($log);
                        $count++;
                    } catch (\Throwable $e) {
                        Log::warning('Failed projecting NotificationLog row', [
                            'notification_log_id' => $log->id,
                            'polaris_log_id'      => $log->polaris_log_id,
                            'error'               => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    /**
     * Decide which lifecycle event type to use, based on simplified status.
     */
    protected function mapStatusToEventType(NotificationLog $log): string
    {
        return match ($log->status) {
            'completed' => NotificationEvent::TYPE_DELIVERED,
            'failed'    => NotificationEvent::TYPE_FAILED,
            default     => NotificationEvent::TYPE_QUEUED,
        };
    }

    /**
     * Build a channel-aware human-readable status string.
     */
    protected function mapStatusText(NotificationLog $log): string
    {
        $base = $log->status_description
            ?: config("notices.notification_statuses.{$log->notification_status_id}", 'Unknown');

        $channel = match ($log->delivery_option_id) {
            1       => 'Mail',
            2       => 'Email',
            3       => 'Voice',
            8       => 'SMS',
            default => 'Notification',
        };

        return trim($channel . ' ' . $base);
    }

    /**
     * Enrich notification with PhoneNotices data if available.
     */
    protected function enrichFromPhoneNotices(Notification $notification): void
    {
        if (!$notification->patron_barcode || !$notification->notice_date) {
            return;
        }

        // Find matching PhoneNotices record
        $phoneNotice = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where('patron_barcode', $notification->patron_barcode)
            ->whereDate('import_date', $notification->notice_date)
            ->first();

        if (!$phoneNotice) {
            return;
        }

        // Enrich with item/bib/hold IDs
        $notification->item_barcode          = $notification->item_barcode ?: $phoneNotice->item_barcode;
        $notification->item_record_id        = $notification->item_record_id ?: $phoneNotice->item_record_id;
        $notification->bib_record_id         = $notification->bib_record_id ?: $phoneNotice->bib_record_id;
        $notification->sys_hold_request_id   = $notification->sys_hold_request_id ?: $phoneNotice->sys_hold_request_id;

        // Enrich with detailed type/level (especially for overdue disambiguation)
        if ($phoneNotice->notification_type_id) {
            $notification->notification_type_id = $phoneNotice->notification_type_id;
            // Derive notification_level from type_id if it's an overdue variant
            $notification->notification_level = $this->deriveNotificationLevel($phoneNotice->notification_type_id);
        }

        // Enrich with site/branch context
        $notification->site_code                 = $notification->site_code ?: $phoneNotice->library_code;
        $notification->site_name                 = $notification->site_name ?: $phoneNotice->library_name;
        $notification->reporting_org_id          = $notification->reporting_org_id ?: $phoneNotice->reporting_org_id;
        $notification->pickup_area_description   = $notification->pickup_area_description ?: null; // PhoneNotices doesn't populate this in schema, leaving for exports

        // Enrich with due_date (for overdues/renewals)
        $notification->due_date                  = $notification->due_date ?: $phoneNotice->notice_date;

        // Enrich with title
        $notification->browse_title              = $notification->browse_title ?: $phoneNotice->title;

        // Enrich with account balance (for fines/bills)
        if ($phoneNotice->account_balance) {
            $notification->account_balance = $phoneNotice->account_balance;
        }

        // Better patron snapshots from PhoneNotices if NotificationLog was sparse
        $notification->patron_name_first = $notification->patron_name_first ?: $phoneNotice->first_name;
        $notification->patron_name_last  = $notification->patron_name_last  ?: $phoneNotice->last_name;
        $notification->patron_email      = $notification->patron_email      ?: $phoneNotice->email;
        $notification->patron_phone      = $notification->patron_phone      ?: $phoneNotice->phone_number;

        $notification->save();

        // Record a lifecycle event indicating that Polaris queued this
        // notice and wrote it to PhoneNotices.
        $this->recordPhoneNoticesEvent($notification, $phoneNotice);
    }

    /**
     * Enrich notification with export table data (holds/overdue/renew).
     */
    protected function enrichFromExportTables(Notification $notification): void
    {
        if (!$notification->patron_barcode || !$notification->notice_date) {
            return;
        }

        // Enrich from holds exports if type = 2 (Hold)
        if ($notification->notification_type_id === 2) {
            $this->enrichFromHoldsExport($notification);
        }

        // Enrich from overdue exports if type in 1,8,11,12,13
        if (in_array($notification->notification_type_id, [1, 8, 11, 12, 13], true)) {
            $this->enrichFromOverdueExport($notification);
        }

        // Enrich from renewal exports if type = 7
        if ($notification->notification_type_id === 7) {
            $this->enrichFromRenewalExport($notification);
        }

        // Record Shoutbomb submission and delivery/failure events
        $this->recordSubmissionAndDeliveryEvents($notification);
    }

    /**
     * Enrich from notifications_holds table.
     */
    protected function enrichFromHoldsExport(Notification $notification): void
    {
        $hold = \\Dcplibrary\\Notices\\Models\\NotificationHold::where('patron_barcode', $notification->patron_barcode)
            ->whereDate('export_timestamp', $notification->notice_date)
            ->first();

        if (!$hold) {
            return;
        }

        $notification->browse_title           = $notification->browse_title ?: $hold->browse_title;
        $notification->sys_hold_request_id    = $notification->sys_hold_request_id ?: $hold->sys_hold_request_id;
        $notification->held_until             = $notification->held_until ?: $hold->hold_till_date;
        $notification->pickup_organization_id = $notification->pickup_organization_id ?: $hold->pickup_organization_id;

        $notification->save();

        $this->recordHoldExportEvent($notification, $hold);
    }

    /**
     * Enrich from notifications_overdue table.
     */
    protected function enrichFromOverdueExport(Notification $notification): void
    {
        $overdue = \\Dcplibrary\\Notices\\Models\\NotificationOverdue::where('patron_barcode', $notification->patron_barcode)
            ->whereDate('export_timestamp', $notification->notice_date)
            ->first();

        if (!$overdue) {
            return;
        }

        $notification->item_barcode        = $notification->item_barcode ?: $overdue->item_barcode;
        $notification->item_record_id      = $notification->item_record_id ?: $overdue->item_record_id;
        $notification->bib_record_id       = $notification->bib_record_id ?: $overdue->bibliographic_record_id;
        $notification->browse_title        = $notification->browse_title ?: $overdue->title;
        $notification->due_date            = $notification->due_date ?: $overdue->due_date;

        $notification->save();

        $this->recordOverdueExportEvent($notification, $overdue);
    }

    /**
     * Enrich from notifications_renewal table.
     */
    protected function enrichFromRenewalExport(Notification $notification): void
    {
        $renewal = \\Dcplibrary\\Notices\\Models\\NotificationRenewal::where('patron_barcode', $notification->patron_barcode)
            ->whereDate('export_timestamp', $notification->notice_date)
            ->first();

        if (!$renewal) {
            return;
        }

        $notification->item_barcode        = $notification->item_barcode ?: $renewal->item_barcode;
        $notification->item_record_id      = $notification->item_record_id ?: $renewal->item_record_id;
        $notification->bib_record_id       = $notification->bib_record_id ?: $renewal->bibliographic_record_id;
        $notification->browse_title        = $notification->browse_title ?: $renewal->title;
        $notification->due_date            = $notification->due_date ?: $renewal->due_date;

        $notification->save();

        $this->recordRenewalExportEvent($notification, $renewal);
    }

    /**
     * Record a lifecycle event from PhoneNotices baseline.
     */
    protected function recordPhoneNoticesEvent(Notification $notification, \\Dcplibrary\\Notices\\Models\\PolarisPhoneNotice $phoneNotice): void
    {
        NotificationEvent::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'source_table'    => 'polaris_phone_notices',
                'source_id'       => $phoneNotice->id,
            ],
            [
                'event_type'         => NotificationEvent::TYPE_PHONENOTICES_RECORDED,
                'event_at'           => $phoneNotice->notice_date ?? $notification->notice_date,
                'delivery_option_id' => $phoneNotice->delivery_option_id,
                'status_code'        => (string) ($phoneNotice->notification_type_id ?? ''),
                'status_text'        => $this->buildPhoneNoticesStatusText($phoneNotice),
                'source_file'        => $phoneNotice->source_file,
                'import_job_id'      => null,
            ]
        );
    }

    /**
     * Build a human-readable status text for PhoneNotices events.
     */
    protected function buildPhoneNoticesStatusText(\\Dcplibrary\\Notices\\Models\\PolarisPhoneNotice $phoneNotice): string
    {
        $typeId   = $phoneNotice->notification_type_id;
        $typeName = $typeId !== null
            ? config("notices.notification_types.{$typeId}", "Type {$typeId}")
            : 'Unknown type';

        $channel = match ($phoneNotice->delivery_type) {
            'voice' => 'Voice',
            'text'  => 'SMS',
            default => 'Notification',
        };

        return trim("{$channel} queued in PhoneNotices – {$typeName}");
    }

    /**
     * Record a hold export event from notifications_holds.
     */
    protected function recordHoldExportEvent(
        Notification $notification,
        \\Dcplibrary\\Notices\\Models\\NotificationHold $hold
    ): void {
        $typeId   = $notification->notification_type_id;
        $typeName = $typeId !== null
            ? config("notices.notification_types.{$typeId}", "Type {$typeId}")
            : 'Unknown type';

        $statusText = "Hold export written – {$typeName}";

        NotificationEvent::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'source_table'    => 'notifications_holds',
                'source_id'       => $hold->id,
            ],
            [
                'event_type'         => NotificationEvent::TYPE_EXPORTED,
                'event_at'           => $hold->export_timestamp,
                'delivery_option_id' => $hold->delivery_option_id,
                'status_code'        => (string) ($typeId ?? ''),
                'status_text'        => $statusText,
                'source_file'        => $hold->source_file,
                'import_job_id'      => null,
            ]
        );
    }

    /**
     * Record an overdue export event from notifications_overdue.
     */
    protected function recordOverdueExportEvent(
        Notification $notification,
        \\Dcplibrary\\Notices\\Models\\NotificationOverdue $overdue
    ): void {
        $typeId   = $notification->notification_type_id;
        $typeName = $typeId !== null
            ? config("notices.notification_types.{$typeId}", "Type {$typeId}")
            : 'Unknown type';

        $statusText = "Overdue export written – {$typeName}";

        NotificationEvent::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'source_table'    => 'notifications_overdue',
                'source_id'       => $overdue->id,
            ],
            [
                'event_type'         => NotificationEvent::TYPE_EXPORTED,
                'event_at'           => $overdue->export_timestamp,
                'delivery_option_id' => $overdue->delivery_option_id,
                'status_code'        => (string) ($typeId ?? ''),
                'status_text'        => $statusText,
                'source_file'        => $overdue->source_file,
                'import_job_id'      => null,
            ]
        );
    }

    /**
     * Record a renewal export event from notifications_renewal.
     */
    protected function recordRenewalExportEvent(
        Notification $notification,
        \\Dcplibrary\\Notices\\Models\\NotificationRenewal $renewal
    ): void {
        $typeId   = $notification->notification_type_id;
        $typeName = $typeId !== null
            ? config("notices.notification_types.{$typeId}", "Type {$typeId}")
            : 'Unknown type';

        $statusText = "Renewal export written – {$typeName}";

        NotificationEvent::updateOrCreate(
            [
                'notification_id' => $notification->id,
                'source_table'    => 'notifications_renewal',
                'source_id'       => $renewal->id,
            ],
            [
                'event_type'         => NotificationEvent::TYPE_EXPORTED,
                'event_at'           => $renewal->export_timestamp,
                'delivery_option_id' => $renewal->delivery_option_id,
                'status_code'        => (string) ($typeId ?? ''),
                'status_text'        => $statusText,
                'source_file'        => $renewal->source_file,
                'import_job_id'      => null,
            ]
        );
    }

    /**
     * After export enrichment, record submission and delivery/failure events
     * from Shoutbomb submissions, deliveries and failure reports.
     */
    protected function recordSubmissionAndDeliveryEvents(Notification $notification): void
    {
        // 1) Shoutbomb submissions → TYPE_SUBMITTED
        $submission = \Dcplibrary\Notices\Models\ShoutbombSubmission::where('patron_barcode', $notification->patron_barcode)
            ->whereDate('submitted_at', $notification->notice_date)
            ->orderBy('submitted_at')
            ->first();

        if ($submission) {
            $statusText = sprintf(
                'Submitted to Shoutbomb – %s',
                $submission->notification_type ?? 'unknown type'
            );

            NotificationEvent::updateOrCreate(
                [
                    'notification_id' => $notification->id,
                    'source_table'    => 'shoutbomb_submissions',
                    'source_id'       => $submission->id,
                ],
                [
                    'event_type'         => NotificationEvent::TYPE_SUBMITTED,
                    'event_at'           => $submission->submitted_at,
                    'delivery_option_id' => $notification->delivery_option_id,
                    'status_code'        => (string) ($notification->notification_type_id ?? ''),
                    'status_text'        => $statusText,
                    'source_file'        => $submission->source_file,
                    'import_job_id'      => null,
                ]
            );
        }

        // 2) Shoutbomb deliveries → TYPE_DELIVERED / TYPE_FAILED
        // Match by barcode + date window
        $deliveries = \Dcplibrary\Notices\Models\ShoutbombDelivery::forPatron($notification->patron_barcode)
            ->whereDate('sent_date', $notification->notice_date)
            ->get();

        foreach ($deliveries as $delivery) {
            $eventType = $delivery->isDelivered()
                ? NotificationEvent::TYPE_DELIVERED
                : NotificationEvent::TYPE_FAILED;

            $statusText = sprintf(
                '%s %s via Shoutbomb',
                $delivery->delivery_type,
                $delivery->status
            );

            NotificationEvent::updateOrCreate(
                [
                    'notification_id' => $notification->id,
                    'source_table'    => 'shoutbomb_deliveries',
                    'source_id'       => $delivery->id,
                ],
                [
                    'event_type'         => $eventType,
                    'event_at'           => $delivery->sent_date,
                    'delivery_option_id' => $notification->delivery_option_id,
                    'status_code'        => $delivery->status,
                    'status_text'        => $statusText,
                    'source_file'        => $delivery->report_file,
                    'import_job_id'      => null,
                ]
            );
        }

        // 3) Failure reports → TYPE_FAILED (email-based failures)
        $failure = \Dcplibrary\Notices\Models\NoticeFailureReport::forPatron($notification->patron_barcode)
            ->around(\Carbon\Carbon::parse($notification->notice_date), 24)
            ->first();

        if ($failure) {
            $statusText = sprintf(
                'Shoutbomb failure: %s',
                $failure->failure_reason ?: $failure->failure_type ?: 'Unknown reason'
            );

            NotificationEvent::updateOrCreate(
                [
                    'notification_id' => $notification->id,
                    'source_table'    => $failure->getTable(),
                    'source_id'       => $failure->id,
                ],
                [
                    'event_type'         => NotificationEvent::TYPE_FAILED,
                    'event_at'           => $failure->received_at,
                    'delivery_option_id' => $notification->delivery_option_id,
                    'status_code'        => $failure->failure_type ?? 'failed',
                    'status_text'        => $statusText,
                    'source_file'        => $failure->subject,
                    'import_job_id'      => null,
                ]
            );
        }
    }

    /**
     * Derive notification_level from notification_type_id.
     * Mainly for overdues (1=1st, 12=2nd, 13=3rd).
     */
    protected function deriveNotificationLevel(int $typeId): ?int
    {
        return match ($typeId) {
            1, 8, 11 => 1, // 1st overdue, fine, bill
            12       => 2, // 2nd overdue
            13       => 3, // 3rd overdue
            default  => null,
        };
    }
}
