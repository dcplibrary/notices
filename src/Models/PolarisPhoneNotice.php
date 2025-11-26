<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Dcplibrary\Notices\Database\Factories\PolarisPhoneNoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PolarisPhoneNotice Model.
 *
 * Represents a row from PhoneNotices.csv (Polaris export).
 *
 * This is VERIFICATION data that confirms Polaris queued a notice and
 * attempted to hand it off to Shoutbomb.
 */
class PolarisPhoneNotice extends Model
{
    use HasFactory;

    protected $table = 'polaris_phone_notices';

    protected static function newFactory()
    {
        return PolarisPhoneNoticeFactory::new();
    }

    /**
     * Mass assignable attributes matching the PhoneNotices.csv mapping.
     */
    protected $fillable = [
        'delivery_type',
        'language',
        'patron_barcode',
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'library_code',
        'library_name',
        'item_barcode',
        'notice_date',
        'title',
        'organization_code',
        'language_code',
        'patron_id',
        'item_record_id',
        'bib_record_id',
        'notification_type_id',
        'delivery_option_id',
        'sys_hold_request_id',
        'account_balance',
        'source_file',
        'import_date',
        'imported_at',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'import_date' => 'date',
        'patron_id' => 'integer',
        'item_record_id' => 'integer',
        'bib_record_id' => 'integer',
        'notification_type_id' => 'integer',
        'delivery_option_id' => 'integer',
        'sys_hold_request_id' => 'integer',
        'account_balance' => 'float',
        'imported_at' => 'datetime',
    ];

    /**
     * Scope to filter by delivery type (voice/text).
     */
    public function scopeWhereDeliveryType($query, string $type)
    {
        return $query->where('delivery_type', $type);
    }

    /**
     * Scope to filter by date range (notice_date).
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('notice_date', [$startDate, $endDate]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('notice_date', $date);
    }

    /**
     * Scope to filter by patron.
     */
    public function scopeForPatron($query, string $barcode)
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope to filter by phone number.
     */
    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone_number', $phone);
    }

    /**
     * Scope to filter by library.
     */
    public function scopeForLibrary($query, string $code)
    {
        return $query->where('library_code', $code);
    }

    /**
     * Get the full patron name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
