<?php

namespace Dcplibrary\Notices\Support;

use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\NotificationActivityLog;

/**
 * Read-model helper for per-notification activity logs.
 *
 * This repository hides the Eloquent/DB details and returns a simple
 * array structure that the Notification Details modal can consume
 * directly as `notification.activity_log`.
 */
class NotificationActivityRepository
{
    /**
     * Get ordered activity entries for a single notification.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forNotification(Notification $notification): array
    {
        $logs = NotificationActivityLog::query()
            ->with('activityFile')
            ->where('notification_id', $notification->id)
            ->orderBy('occurred_at')
            ->get()
        ;

        return $logs->map(function (NotificationActivityLog $log): array {
            return [
                'timestamp'        => $log->occurred_at?->format('M j, Y g:i A'),
                'filename'         => $log->activityFile?->original_name,
                'activity_file_id' => $log->activityFile?->id,
                'file_type'        => $log->activityFile?->type,
                'file_meta'        => $log->activityFile?->metadata,
                'type'             => $log->source,
                'description'      => $log->description,
                'source'           => $log->source,
                'details'          => $log->meta,
            ];
        })->all();
    }
}
