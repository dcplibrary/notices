<?php

namespace Dcplibrary\Notices\Models;

use Dcplibrary\Notices\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Raw overdue/fine/bill notification exports from Polaris.
 *
 * Stores queue-based overdue notifications at various stages:
 * - 1st Overdue (1 day after due)
 * - 2nd Overdue (7 days after due)
 * - 3rd Overdue (14 days after due)
 * - Bill (21 days after due)
 * - Collection Agency (42 days after due - final notice)
 */
class PolarisOverdueNotification extends Model
{
    use HasUtcTimestamps;

    protected $table = 'polaris_overdue_notifications';

    protected $guarded = [];

    protected $casts = [
        'patron_id'               => 'integer',
        'item_record_id'          => 'integer',
        'notification_type_id'    => 'integer',
        'delivery_option_id'      => 'integer',
        'notification_queue_id'   => 'integer',
        'processed'               => 'boolean',
        'bibliographic_record_id' => 'integer',
        'patron_record_status_id' => 'integer',
        'patron_expiration_date'  => 'date',
        'due_date'                => 'date',
        'days_until_due'          => 'integer',
        'renewals'                => 'integer',
        'renewal_limit'           => 'integer',
        'material_type_id'        => 'integer',
        'checkout_branch_id'      => 'integer',
        'loan_units'              => 'integer',
        'sys_hold_request_id'     => 'integer',
        'sys_hold_status_id'      => 'integer',
        'activation_date'         => 'date',
        'hold_notification_date'  => 'date',
        'hold_till_date'          => 'date',
        'pickup_organization_id'  => 'integer',
        'hold_pickup_area_id'     => 'integer',
        'suspended'               => 'boolean',
        'system_blocks'           => 'integer',
        'ovd_notice_count'        => 'integer',
        'billing_notice_sent'     => 'boolean',
    ];
}
