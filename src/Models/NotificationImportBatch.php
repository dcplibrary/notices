<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groups notifications imported together in a single FTP file or export session.
 *
 * Import batches give each raw notification a shared identifier (filename,
 * date_for, source) so that deduplication and reconciliation can reason about
 * which records came from the same file run. Notifications are linked back via
 * `import_batch_id`.
 *
 * @property int                                                        $id
 * @property string                                                     $identifier    Filename or batch key (e.g. "holds_2025-01-15_08-05-00.txt")
 * @property string                                                     $source_type   "ftp" | "email" | etc.
 * @property \Carbon\Carbon                                             $date_for      Logical date this batch represents
 * @property null|\Carbon\Carbon                                        $imported_at   When the batch was fully ingested
 * @property \Carbon\Carbon                                             $created_at
 * @property \Carbon\Carbon                                             $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int,Notification> $notifications
 */
class NotificationImportBatch extends Model
{
    protected $table = 'notification_import_batches';

    protected $guarded = [];

    protected $casts = [
        'date_for'    => 'date',
        'imported_at' => 'datetime',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'import_batch_id');
    }
}
