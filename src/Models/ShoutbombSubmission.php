<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoutbombSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_type',
        'patron_barcode',
        'phone_number',
        'title',
        'item_id',
        'branch_id',
        'pickup_date',
        'expiration_date',
        'item_record_id',
        'renewals',
        'bibliographic_record_id',
        'renewal_limit',
        'submitted_at',
        'source_file',
        'delivery_type',
        'imported_at',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'expiration_date' => 'date',
        'submitted_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    /**
     * Scope to filter by notification type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope to filter by delivery type (voice/text).
     */
    public function scopeByDeliveryType($query, string $type)
    {
        return $query->where('delivery_type', $type);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('submitted_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get recent submissions.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('submitted_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter by patron.
     */
    public function scopeForPatron($query, string $barcode)
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Get holds submissions.
     */
    public function scopeHolds($query)
    {
        return $query->where('notification_type', 'holds');
    }

    /**
     * Get overdue submissions.
     */
    public function scopeOverdues($query)
    {
        return $query->where('notification_type', 'overdue');
    }

    /**
     * Get renewal submissions.
     */
    public function scopeRenewals($query)
    {
        return $query->where('notification_type', 'renew');
    }

    /**
     * Get voice notifications.
     */
    public function scopeVoice($query)
    {
        return $query->where('delivery_type', 'voice');
    }

    /**
     * Get text notifications.
     */
    public function scopeText($query)
    {
        return $query->where('delivery_type', 'text');
    }
}
