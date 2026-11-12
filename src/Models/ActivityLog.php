<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic audit-trail entry for any model in the application.
 *
 * Records who did what to which subject, along with optional JSON metadata.
 * Both the subject (the thing that changed) and the causer (the user or system
 * process that triggered the change) are polymorphic relations, so this table
 * can serve any model without schema changes.
 *
 * @property int            $id
 * @property string         $log_name     Category bucket (e.g. "default", "notices")
 * @property null|string    $description  Human-readable event description
 * @property null|string    $subject_type Morph class of the changed model
 * @property null|int       $subject_id   Morph ID of the changed model
 * @property null|string    $causer_type  Morph class of the acting user or process
 * @property null|int       $causer_id    Morph ID of the acting user or process
 * @property null|array     $meta         Arbitrary key-value context (JSON)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
