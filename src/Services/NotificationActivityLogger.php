<?php

namespace Dcplibrary\Notices\Services;

use Carbon\CarbonInterface;
use Dcplibrary\Notices\Models\ActivityFile;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationActivityLog;

class NotificationActivityLogger
{
    /**
     * Log an activity event for a notification.
     *
     * This is the central entry point for recording per-notification activity.
     * It does not decide *when* to log; callers in import/reconciliation
     * pipelines decide which state transitions or confirmations should create
     * an entry, then call this helper to persist a normalized record.
     *
     * @param Notification                $notification notification being modified/enriched
     * @param string                      $source       high-level source key: export_file,
     *                                                  notification_log, summary_report,
     *                                                  shoutbomb_failure, issue_detector, user, etc
     * @param string                      $description  human-readable description of what happened
     * @param null|ActivityFile           $file         optional related activity file
     * @param null|CarbonInterface|string $occurredAt   when this event occurred (defaults to now())
     * @param array                       $meta         Optional structured metadata (status ids,
     *                                                  failure types, issue ids, etc.).
     * @param null|string                 $lineType     Optional canonical line type
     *                                                  (e.g. "delivery_confirmation").
     * @param null|int                    $lineId       optional canonical line id
     */
    public function log(
        Notification $notification,
        string $source,
        string $description,
        ?ActivityFile $file = null,
        $occurredAt = null,
        array $meta = [],
        ?string $lineType = null,
        ?int $lineId = null,
    ): NotificationActivityLog {
        $occurred = $occurredAt instanceof CarbonInterface
            ? $occurredAt
            : ($occurredAt ? now()->parse($occurredAt) : now());

        return NotificationActivityLog::create([
            'notification_id'  => $notification->id,
            'occurred_at'      => $occurred,
            'source'           => $source,
            'activity_file_id' => $file?->id,
            'line_type'        => $lineType,
            'line_id'          => $lineId,
            'description'      => $description,
            'meta'             => $meta,
        ]);
    }
}
