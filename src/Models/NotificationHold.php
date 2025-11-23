<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationHold Model
 *
 * Stores hold notifications sent to ShoutBomb from holds*.txt exports.
 * notification_type_id is ALWAYS 2 (Hold) - inferred from filename.
 */
class NotificationHold extends Model
{
    protected $table = 'notifications_holds';

    protected $fillable = [
        'browse_title',
        'creation_date',
        'sys_hold_request_id',
        'patron_id',
        'pickup_organization_id',
        'hold_till_date',
        'patron_barcode',
        'notification_type_id',
        'delivery_option_id',
        'export_timestamp',
        'source_file',
        'imported_at',
    ];

    protected $casts = [
        'creation_date' => 'date',
        'hold_till_date' => 'date',
        'patron_id' => 'integer',
        'pickup_organization_id' => 'integer',
        'sys_hold_request_id' => 'integer',
        'notification_type_id' => 'integer',
        'delivery_option_id' => 'integer',
        'export_timestamp' => 'datetime',
        'imported_at' => 'datetime',
    ];

    /**
     * Notification type is always 2 (Hold) for this table.
     */
    public const NOTIFICATION_TYPE_ID = 2;

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
     * Scope to filter by hold request ID.
     */
    public function scopeForHoldRequest($query, int $holdRequestId)
    {
        return $query->where('sys_hold_request_id', $holdRequestId);
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
