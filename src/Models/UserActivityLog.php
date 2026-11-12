<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Records a staff or system action taken within the notices application.
 *
 * UserActivityLogs capture user-initiated events (e.g. resolving a patron issue,
 * triggering a manual import, changing settings) alongside the JobRun and
 * Notification that were involved. The actor is polymorphic so it can point at
 * any User model the host application provides.
 *
 * @property int                 $id
 * @property string              $action          Event name (e.g. "issue_resolved", "import_triggered")
 * @property null|int            $notification_id FK → notifications (if applicable)
 * @property null|int            $job_run_id      FK → job_runs (if triggered by a job)
 * @property null|string         $user_type       Morph class of the acting user
 * @property null|int            $user_id         Morph ID of the acting user
 * @property null|\Carbon\Carbon $occurred_at     When the action occurred
 * @property null|array          $meta            Additional event context (JSON)
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 * @property null|Notification   $notification
 * @property null|JobRun         $jobRun
 */
class UserActivityLog extends Model
{
    protected $table = 'user_activity_logs';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function jobRun(): BelongsTo
    {
        return $this->belongsTo(JobRun::class, 'job_run_id');
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
