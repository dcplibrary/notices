<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris sys_hold_statuses lookup table.
 *
 * Maps Polaris SysHoldStatusID values to their short machine names and
 * human-readable descriptions (e.g. 3 → "Held" | "Held - Ready for Pickup").
 * Used in hold notification views to indicate where an item is in the hold queue.
 *
 * @property int    $id          Polaris SysHoldStatusID
 * @property string $name        Short machine-friendly name (e.g. "Held")
 * @property string $description Full human-readable description
 */
class SysHoldStatus extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'description', 'name'];

    protected $casts = [
        'id' => 'integer',
    ];
}
