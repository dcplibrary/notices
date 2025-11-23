<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Dcplibrary\Notices\Database\Factories\PolarisPhoneNoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PolarisPhoneNotice Model
 *
 * Represents data from PhoneNotices.csv - a Polaris-generated export file
 * used for VERIFICATION of notices sent to Shoutbomb.
 *
 * This is the VALIDATION BASELINE - what Polaris queued for notification.
 * Contains all 25 CSV fields as defined in the spec.
 */
class PolarisPhoneNotice extends Model
{
    use HasFactory;

    protected $table = 'polaris_phone_notices';

    protected static function newFactory()
    {
        return PolarisPhoneNoticeFactory::new();
    }

    protected $fillable = [
        // CSV Fields 1-5
        'delivery_method',
        'language',
        'notice_type',
        'notification_level',
        'patron_barcode',
        // CSV Fields 6-10
        'patron_title',
        'name_first',
        'name_last',
        'phone_number',
        'email_address',
        // CSV Fields 11-15
        'site_code',
        'site_name',
        'item_barcode',
        'due_date',
        'browse_title',
        // CSV Fields 16-20
        'reporting_org_id',
        'language_id',
        'notification_type_id',
        'delivery_option_id',
        'patron_id',
        // CSV Fields 21-25
        'item_record_id',
        'sys_hold_request_id',
        'pickup_area_description',
        'txn_id',
        'account_balance',
        // Tracking fields
        'import_date',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'notice_type' => 'integer',
        'notification_level' => 'integer',
        'due_date' => 'date',
        'reporting_org_id' => 'integer',
        'language_id' => 'integer',
        'notification_type_id' => 'integer',
        'delivery_option_id' => 'integer',
        'patron_id' => 'integer',
        'item_record_id' => 'integer',
        'sys_hold_request_id' => 'integer',
        'txn_id' => 'integer',
        'account_balance' => 'decimal:2',
        'import_date' => 'date',
        'imported_at' => 'datetime',
    ];

    /**
     * Scope to filter by delivery method.
     */
    public function scopeVoice($query)
    {
        return $query->where('delivery_method', 'V');
    }

    public function scopeText($query)
    {
        return $query->where('delivery_method', 'T');
    }

    /**
     * Scope by notification type.
     */
    public function scopeHolds($query)
    {
        return $query->where('notification_type_id', 2);
    }

    public function scopeOverdues($query)
    {
        return $query->whereIn('notification_type_id', [1, 12, 13]);
    }

    public function scopeRenewals($query)
    {
        return $query->where('notification_type_id', 7);
    }

    public function scopeFines($query)
    {
        return $query->where('notification_type_id', 8);
    }

    public function scopeBills($query)
    {
        return $query->where('notification_type_id', 11);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('import_date', '>=', now()->subDays($days));
    }

    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('import_date', [$startDate, $endDate]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('import_date', $date);
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
        return $query->where('site_code', $code);
    }

    /**
     * Get the full patron name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->name_first} {$this->name_last}");
    }

    /**
     * Check if this is a voice notification.
     */
    public function isVoice(): bool
    {
        return $this->delivery_method === 'V' || $this->delivery_option_id === 3;
    }

    /**
     * Check if this is a text/SMS notification.
     */
    public function isText(): bool
    {
        return $this->delivery_method === 'T' || $this->delivery_option_id === 8;
    }

    /**
     * Get human-readable notification type.
     */
    public function getNotificationTypeNameAttribute(): string
    {
        return match ($this->notification_type_id) {
            1 => '1st Overdue',
            2 => 'Hold',
            7 => 'Renewal Reminder',
            8 => 'Fine',
            11 => 'Bill',
            12 => '2nd Overdue',
            13 => '3rd Overdue',
            default => 'Unknown',
        };
    }

    /**
     * Get human-readable delivery method.
     */
    public function getDeliveryMethodNameAttribute(): string
    {
        return $this->isVoice() ? 'Voice' : ($this->isText() ? 'Text' : 'Unknown');
    }
}
