<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NoticeFailureReport Model
 *
 * Stores delivery failures from ShoutBomb with enrichment capabilities.
 * Parsed from email reports:
 *   - "Invalid patron phone number [Date]"
 *   - "Voice notices that were not delivered on [Date]"
 */
class NoticeFailureReport extends Model
{
    protected $table = 'notice_failure_reports';

    protected $fillable = [
        // Email source
        'outlook_message_id',
        'subject',
        'from_address',
        // Patron/Contact info
        'patron_phone',
        'patron_id',
        'patron_barcode',
        'barcode_partial',
        'patron_name',
        'contact_type',
        'contact_value',
        // Enrichment fields
        'notification_type_id',
        'delivery_option_id',
        'item_record_id',
        'sys_hold_request_id',
        'bibliographic_record_id',
        // Timestamps
        'notification_queued_at',
        'notification_sent_at',
        'export_timestamp',
        // Failure details
        'delivery_method',
        'failure_type',
        'failure_reason',
        'failure_category',
        'account_status',
        'notice_description',
        'attempt_count',
        // FK references
        'phone_notices_import_id',
        'notification_export_id',
        // Processing
        'received_at',
        'processed_at',
        'raw_content',
    ];

    protected $casts = [
        'patron_id' => 'integer',
        'barcode_partial' => 'boolean',
        'notification_type_id' => 'integer',
        'delivery_option_id' => 'integer',
        'item_record_id' => 'integer',
        'sys_hold_request_id' => 'integer',
        'bibliographic_record_id' => 'integer',
        'attempt_count' => 'integer',
        'phone_notices_import_id' => 'integer',
        'notification_export_id' => 'integer',
        'notification_queued_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'export_timestamp' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Relationship to PolarisPhoneNotice (for enrichment).
     */
    public function phoneNotice(): BelongsTo
    {
        return $this->belongsTo(PolarisPhoneNotice::class, 'phone_notices_import_id');
    }

    /**
     * Scope by notice type (e.g., 'sms' or 'voice').
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('delivery_method', $type);
    }

    /**
     * Scope by failure type.
     */
    public function scopeInvalidPhone($query)
    {
        return $query->where('failure_type', 'invalid');
    }

    public function scopeOptedOut($query)
    {
        return $query->where('failure_type', 'opted-out');
    }

    public function scopeVoiceNotDelivered($query)
    {
        return $query->where('failure_type', 'voice-not-delivered');
    }

    /**
     * Scope by delivery method.
     */
    public function scopeVoice($query)
    {
        return $query->where('delivery_method', 'voice');
    }

    public function scopeSms($query)
    {
        return $query->where('delivery_method', 'sms');
    }

    /**
     * Scope by normalized phone (digits only, compare last 10).
     */
    public function scopeForPhone(Builder $query, string $phone): Builder
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        return $query->where('patron_phone', substr($digits, -10));
    }

    /**
     * Scope by date range.
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('received_at', [$startDate, $endDate]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('received_at', $date);
    }

    /**
     * Scope by date window around a target.
     */
    public function scopeAround(Builder $query, Carbon $center, int $hours = 24): Builder
    {
        return $query->whereBetween('received_at', [
            $center->copy()->subHours($hours),
            $center->copy()->addHours($hours),
        ]);
    }

    /**
     * Scope by patron.
     */
    public function scopeForPatron($query, string $barcode)
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope for records needing enrichment.
     */
    public function scopeNeedsEnrichment($query)
    {
        return $query->whereNull('notification_type_id')
            ->orWhereNull('phone_notices_import_id');
    }

    /**
     * Check if this failure has been enriched.
     */
    public function isEnriched(): bool
    {
        return $this->phone_notices_import_id !== null;
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
     * Get human-readable failure category.
     */
    public function getFailureCategoryDisplayAttribute(): string
    {
        return match ($this->failure_category) {
            'invalid_number' => 'Invalid Phone Number',
            'opted_out' => 'Patron Opted Out',
            'no_answer' => 'No Answer',
            'busy' => 'Line Busy',
            'disconnected' => 'Number Disconnected',
            'carrier_blocked' => 'Blocked by Carrier',
            default => $this->failure_category ?? 'Unknown',
        };
    }
}
