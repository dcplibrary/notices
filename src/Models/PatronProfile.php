<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatronProfile extends Model
{
    use HasFactory;

    protected $table = 'patron_profiles';

    protected $fillable = [
        'patron_barcode',
        'patron_id',
        'name_first',
        'name_last',
        'primary_phone',
        'email_address',
        'delivery_option_id',
        'former_delivery_option_id',
        'delivery_option_changed_at',
        'language_code',
        'language_id',
        'reporting_org_id',
        'last_seen_in_phonenotices_at',
        'last_seen_in_notification_logs_at',
        'last_seen_in_lists_at',
    ];

    protected $casts = [
        'delivery_option_changed_at' => 'datetime',
        'last_seen_in_phonenotices_at' => 'datetime',
        'last_seen_in_notification_logs_at' => 'datetime',
        'last_seen_in_lists_at' => 'datetime',
    ];

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
