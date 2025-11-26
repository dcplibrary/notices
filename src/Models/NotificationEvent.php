<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $table = 'notification_events';

    public const TYPE_QUEUED = 'queued';

    public const TYPE_EXPORTED = 'exported';

    public const TYPE_SUBMITTED = 'submitted';

    public const TYPE_PHONENOTICES_RECORDED = 'phonenotices_recorded';

    public const TYPE_DELIVERED = 'delivered';

    public const TYPE_FAILED = 'failed';

    public const TYPE_VERIFIED = 'verified';

    protected $fillable = [
        'notification_id',
        'event_type',
        'event_at',
        'delivery_option_id',
        'status_code',
        'status_text',
        'source_table',
        'source_id',
        'source_file',
        'import_job_id',
    ];

    protected $casts = [
        'event_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
