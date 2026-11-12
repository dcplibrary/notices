<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw representation of Polaris renewal/almost due notifications, adhering closely
 * to the export structure from the Polaris renew export query.
 */
class PolarisRenewalNotification extends Model
{
    protected $table = 'polaris_renewal_notifications';

    protected $guarded = [];
}
