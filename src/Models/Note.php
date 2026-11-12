<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A free-text note attached to any model (polymorphic).
 *
 * Notes are used by staff to annotate notifications, patron delivery issues,
 * or other records with context that is not captured elsewhere. Private notes
 * are only visible to staff; public notes may surface in patron-facing views.
 *
 * @property int            $id
 * @property null|string    $subject_type Morph class of the annotated model
 * @property null|int       $subject_id   Morph ID of the annotated model
 * @property null|string    $author_type  Morph class of the note author (typically a User)
 * @property null|int       $author_id    Morph ID of the note author
 * @property string         $body         Note content
 * @property bool           $is_private   Whether the note is staff-only
 * @property null|array     $meta         Optional structured metadata (JSON)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Note extends Model
{
    protected $table = 'notes';

    protected $guarded = [];

    protected $casts = [
        'is_private' => 'boolean',
        'meta'       => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}
