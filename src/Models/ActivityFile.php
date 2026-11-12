<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a raw source file ingested during a notices import job run.
 *
 * Each ActivityFile tracks a single parsed file (FTP export, email attachment, etc.)
 * along with its record count and the JobRun that processed it. Related
 * NotificationActivityLog rows record individual status-change events derived
 * from the file's contents.
 *
 * @property int                                                                   $id
 * @property null|int                                                              $job_run_id   FK → job_runs
 * @property string                                                                $filename     Original filename on disk or FTP
 * @property null|string                                                           $source_type  Import origin (e.g. "ftp", "graph_email")
 * @property int                                                                   $record_count Number of rows parsed from this file
 * @property null|\Carbon\Carbon                                                   $file_date    Logical date the file represents
 * @property null|\Carbon\Carbon                                                   $parsed_at    Timestamp when the file was fully parsed
 * @property null|array                                                            $metadata     Arbitrary per-file metadata (JSON)
 * @property \Carbon\Carbon                                                        $created_at
 * @property \Carbon\Carbon                                                        $updated_at
 * @property null|JobRun                                                           $jobRun
 * @property \Illuminate\Database\Eloquent\Collection<int,NotificationActivityLog> $activityLogs
 */
class ActivityFile extends Model
{
    protected $table = 'activity_files';

    protected $guarded = [];

    protected $casts = [
        'parsed_at' => 'datetime',
        'file_date' => 'datetime',
        'metadata'  => 'array',
    ];

    public function activityLogs(): HasMany
    {
        return $this->hasMany(NotificationActivityLog::class, 'activity_file_id');
    }

    public function jobRun(): BelongsTo
    {
        return $this->belongsTo(JobRun::class, 'job_run_id');
    }
}
