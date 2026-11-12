<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw representation of Polaris hold notifications, adhering closely
 * to the export structure from the Polaris holds export query.
 */
class PolarisHoldNotification extends Model
{
    protected $table = 'polaris_hold_notifications';

    protected $guarded = [];
}
