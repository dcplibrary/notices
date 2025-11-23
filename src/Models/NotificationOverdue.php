<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * NotificationOverdue Model
 *
 * Stores overdue/fine/bill notifications sent to ShoutBomb from overdue*.txt exports.
 * notification_type_id can be: 1, 7, 8, 11, 12, or 13 - MUST be enriched from PhoneNotices.
 */
class NotificationOverdue extends Model
{
    protected $table = 'notifications_overdue';

    protected $fillable = [
        'patron_id',
        'item_barcode',
        'title',
        'due_date',
        'item_record_id',
        'renewals',
        'bibliographic_record_id',
        'renewal_limit',
        'patron_barcode',
        'notification_type_id',
        'delivery_option_id',
        'export_timestamp',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'patron_id' => 'integer',
        'item_record_id' => 'integer',
        'bibliographic_record_id' => 'integer',
        'renewals' => 'integer',
        'renewal_limit' => 'integer',
        'notification_type_id' => 'integer',
        'delivery_option_id' => 'integer',
        'export_timestamp' => 'datetime',
        'imported_at' => 'datetime',
    ];

    /**
     * Valid notification type IDs for overdue files.
     */
    public const VALID_NOTIFICATION_TYPE_IDS = [1, 7, 8, 11, 12, 13];

    /**
     * Scope to filter by patron.
     */
    public function scopeForPatron($query, string $barcode)
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope to filter by item.
     */
    public function scopeForItem($query, int $itemRecordId)
    {
        return $query->where('item_record_id', $itemRecordId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('export_timestamp', [$startDate, $endDate]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('export_timestamp', $date);
    }

    /**
     * Scope by delivery option.
     */
    public function scopeVoice($query)
    {
        return $query->where('delivery_option_id', 3);
    }

    public function scopeText($query)
    {
        return $query->where('delivery_option_id', 8);
    }

    /**
     * Scope by notification type.
     */
    public function scopeFirstOverdue($query)
    {
        return $query->where('notification_type_id', 1);
    }

    public function scopeSecondOverdue($query)
    {
        return $query->where('notification_type_id', 12);
    }

    public function scopeThirdOverdue($query)
    {
        return $query->where('notification_type_id', 13);
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
     * Scope for records needing enrichment.
     */
    public function scopeNeedsEnrichment($query)
    {
        return $query->whereNull('notification_type_id')
            ->orWhereNull('delivery_option_id');
    }

    /**
     * Check if notification type needs enrichment.
     */
    public function needsTypeEnrichment(): bool
    {
        return $this->notification_type_id === null;
    }

    /**
     * Get human-readable notification type.
     */
    public function getNotificationTypeNameAttribute(): string
    {
        return match ($this->notification_type_id) {
            1 => '1st Overdue',
            7 => 'Pre-due/Almost Overdue',
            8 => 'Fine',
            11 => 'Bill',
            12 => '2nd Overdue',
            13 => '3rd Overdue',
            default => 'Unknown',
        };
    }
}
