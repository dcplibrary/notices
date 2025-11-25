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
}
