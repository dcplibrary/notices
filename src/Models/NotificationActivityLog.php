<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a single status-change or pipeline event for a notification.
 *
 * NotificationActivityLog rows are written by the import, reconciliation,
 * and failure-detection pipeline to provide a full audit trail of how a
 * notification moved from raw export → deduplicated → reconciled → delivered/failed.
 *
 * @property int                 $id
 * @property null|int            $notification_id  FK → notifications
 * @property null|int            $job_run_id       FK → job_runs
 * @property null|int            $activity_file_id FK → activity_files
 * @property string              $event            Event name (e.g. "imported", "reconciled", "marked_failed")
 * @property null|string         $from_status      Status before the event
 * @property null|string         $to_status        Status after the event
 * @property null|\Carbon\Carbon $occurred_at      When the event happened (may differ from created_at)
 * @property null|array          $meta             Additional context (JSON)
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 * @property null|Notification   $notification
 * @property null|ActivityFile   $activityFile
 */
class NotificationActivityLog extends Model
{
    protected $table = 'notification_activity_logs';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function activityFile(): BelongsTo
    {
        return $this->belongsTo(ActivityFile::class, 'activity_file_id');
    }
}
