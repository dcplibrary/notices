<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'notification_type_id',
        'notification_level',
        'notification_log_id',
        'patron_barcode',
        'patron_id',
        'item_barcode',
        'item_record_id',
        'bib_record_id',
        'sys_hold_request_id',
        'notice_date',
        'held_until',
        'due_date',
        'delivery_option_id',
        'delivery_string',
        'reporting_org_id',
        'site_code',
        'site_name',
        'pickup_area_description',
        'account_balance',
        'browse_title',
        'call_number',
        'patron_name_first',
        'patron_name_last',
        'patron_email',
        'patron_phone',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'held_until' => 'date',
        'due_date' => 'date',
        'account_balance' => 'decimal:2',
    ];

    public function events()
    {
        return $this->hasMany(NotificationEvent::class);
    }

    public function notificationLog()
    {
        // Link to local NotificationLog by Polaris NotificationLogID
        return $this->belongsTo(NotificationLog::class, 'notification_log_id', 'polaris_log_id');
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
