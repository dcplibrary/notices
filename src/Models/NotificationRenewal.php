<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * NotificationRenewal Model.
 *
 * Stores renewal reminder notifications sent to ShoutBomb from renew*.txt exports.
 * notification_type_id is ALWAYS 7 (Renewal Reminder) - inferred from filename.
 */
class NotificationRenewal extends Model
{
    protected $table = 'notifications_renewal';

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
     * Notification type is always 7 (Renewal Reminder) for this table.
     */
    public const NOTIFICATION_TYPE_ID = 7;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->notification_type_id) {
                $model->notification_type_id = self::NOTIFICATION_TYPE_ID;
            }
        });
    }

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
     * Scope for records needing enrichment.
     */
    public function scopeNeedsEnrichment($query)
    {
        return $query->whereNull('delivery_option_id');
    }

    /**
     * Check if this is a voice notification.
     */
    public function isVoice(): bool
    {
        return $this->delivery_option_id === 3;
    }

    /**
     * Check if this is a text/SMS notification.
     */
    public function isText(): bool
    {
        return $this->delivery_option_id === 8;
    }
}
