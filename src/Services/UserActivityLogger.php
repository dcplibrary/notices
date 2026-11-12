<?php

namespace Dcplibrary\Notices\Services;

use Carbon\CarbonInterface;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\UserActivityLog;

/**
 * Helper service for recording user-driven actions in user_activity_logs.
 */
class UserActivityLogger
{
    /**
     * Log a user action.
     *
     * @param null|int                    $userId       ID of the acting user (nullable for system accounts)
     * @param string                      $action       Short key (resolve_issue, run_import, update_settings, etc.)
     * @param string                      $description  Human-readable description for UI
     * @param null|CarbonInterface|string $occurredAt   When the action occurred (defaults to now())
     * @param array                       $meta         Extra context (route, IP, old/new values, etc.)
     * @param null|Notification           $notification Optional notification affected
     * @param null|int                    $jobRunId     Optional related JobRun id
     */
    public function log(
        ?int $userId,
        string $action,
        string $description,
        $occurredAt = null,
        array $meta = [],
        ?Notification $notification = null,
        ?int $jobRunId = null,
    ): UserActivityLog {
        $occurred = $occurredAt instanceof CarbonInterface
            ? $occurredAt
            : ($occurredAt ? now()->parse($occurredAt) : now());

        return UserActivityLog::create([
            'user_id'         => $userId,
            'user_type'       => null, // app can override with morph if needed
            'notification_id' => $notification?->id,
            'job_run_id'      => $jobRunId,
            'occurred_at'     => $occurred,
            'action'          => $action,
            'description'     => $description,
            'meta'            => $meta,
        ]);
    }
}
