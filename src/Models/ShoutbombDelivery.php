<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Dcplibrary\Notices\Database\Factories\ShoutbombDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoutbombDelivery extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'shoutbomb_deliveries';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patron_barcode',
        'phone_number',
        'delivery_type',
        'message_type',
        'sent_date',
        'status',
        'carrier',
        'failure_reason',
        'report_file',
        'report_type',
        'imported_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sent_date' => 'datetime',
        'imported_at' => 'datetime',
    ];

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('sent_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by delivery type (SMS/Voice).
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('delivery_type', $type);
    }

    /**
     * Scope to get SMS deliveries only.
     */
    public function scopeSms(Builder $query): Builder
    {
        return $query->where('delivery_type', 'SMS');
    }

    /**
     * Scope to get Voice deliveries only.
     */
    public function scopeVoice(Builder $query): Builder
    {
        return $query->where('delivery_type', 'Voice');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get delivered messages.
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'Delivered');
    }

    /**
     * Scope to get failed messages.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'Failed');
    }

    /**
     * Scope to get invalid phone numbers.
     */
    public function scopeInvalid(Builder $query): Builder
    {
        return $query->where('status', 'Invalid');
    }

    /**
     * Scope to filter by patron barcode.
     */
    public function scopeForPatron(Builder $query, string $barcode): Builder
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope to get recent deliveries.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('sent_date', '>=', now()->subDays($days));
    }

    /**
     * Check if delivery was successful.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'Delivered';
    }

    /**
     * Check if delivery failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'Failed';
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return ShoutbombDeliveryFactory::new();
    }
}
