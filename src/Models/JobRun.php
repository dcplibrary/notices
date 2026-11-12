<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tracks a single execution of a notices import or maintenance job.
 *
 * JobRuns are created at the start of any queued or scheduled job and updated
 * with a final status on completion or failure. Each run can be linked to the
 * staff user who triggered it, and may produce one or more ActivityFiles.
 *
 * @property int                                                                   $id
 * @property string                                                                $job_name             Artisan command or queue job class name
 * @property string                                                                $status               "pending" | "running" | "success" | "failed"
 * @property null|string                                                           $trigger_type         "manual" | "schedule" | "queue"
 * @property null|int                                                              $triggered_by_user_id FK → users (optional)
 * @property null|\Carbon\Carbon                                                   $started_at
 * @property null|\Carbon\Carbon                                                   $finished_at
 * @property null|array                                                            $meta                 Arbitrary run metadata (dates, options, output)
 * @property \Carbon\Carbon                                                        $created_at
 * @property \Carbon\Carbon                                                        $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int,ActivityFile>            $activityFiles
 * @property \Illuminate\Database\Eloquent\Collection<int,NotificationActivityLog> $activityLogs
 * @property \Illuminate\Database\Eloquent\Collection<int,UserActivityLog>         $userActivityLogs
 * @property null|Model                                                            $triggeredByUser
 */
class JobRun extends Model
{
    protected $table = 'job_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function activityFiles(): HasMany
    {
        return $this->hasMany(ActivityFile::class, 'job_run_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(NotificationActivityLog::class, 'job_run_id');
    }

    public function userActivityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class, 'job_run_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        // Concrete app can override this to point at its User model if desired.
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'triggered_by_user_id');
    }
}
