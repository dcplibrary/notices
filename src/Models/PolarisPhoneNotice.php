<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw Polaris PhoneNotices (Voice/SMS) export record.
 *
 * Imported from the Polaris PhoneNotices CSV to verify that Voice and SMS
 * notifications were successfully queued for delivery. Used as a cross-reference
 * against Shoutbomb exports during reconciliation.
 *
 * @property int                 $id
 * @property null|string         $patron_id         Polaris PatronID
 * @property null|string         $patron_barcode
 * @property null|string         $notification_type Notification type label from Polaris
 * @property null|string         $phone_number      Patron phone number targeted
 * @property null|\Carbon\Carbon $hold_till_date    For hold notices — expiry date
 * @property null|\Carbon\Carbon $export_datetime   When this record was exported from Polaris
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class PolarisPhoneNotice extends Model
{
    protected $table = 'polaris_phone_notices';

    protected $guarded = [];

    protected $casts = [
        'hold_till_date'  => 'date',
        'export_datetime' => 'datetime',
    ];
}
