<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polaris item_statuses lookup table.
 *
 * Maps Polaris ItemStatusID values to descriptive names and optional UI banner
 * text shown when an item is in a particular state (e.g. "Checked Out", "In Transit").
 *
 * @property int         $id
 * @property string      $description Polaris item status description
 * @property null|string $name        Short machine-friendly name
 * @property null|string $banner_text Optional text to display in the UI for this status
 */
class ItemStatus extends Model
{
    protected $fillable = ['description', 'name', 'banner_text'];

    protected $casts = [
        'id' => 'integer',
    ];
}
