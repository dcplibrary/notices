<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Dcplibrary\Notices\Database\Factories\DailyNotificationSummaryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyNotificationSummary extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'daily_notification_summary';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'summary_date',
        'notification_type_id',
        'delivery_option_id',
        'total_sent',
        'total_success',
        'total_failed',
        'total_pending',
        'total_holds',
        'total_overdues',
        'total_overdues_2nd',
        'total_overdues_3rd',
        'total_cancels',
        'total_recalls',
        'total_bills',
        'unique_patrons',
        'success_rate',
        'failure_rate',
        'aggregated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'summary_date' => 'date',
        'aggregated_at' => 'datetime',
        'total_sent' => 'integer',
        'total_success' => 'integer',
        'total_failed' => 'integer',
        'total_pending' => 'integer',
        'total_holds' => 'integer',
        'total_overdues' => 'integer',
        'total_overdues_2nd' => 'integer',
        'total_overdues_3rd' => 'integer',
        'total_cancels' => 'integer',
        'total_recalls' => 'integer',
        'total_bills' => 'integer',
        'unique_patrons' => 'integer',
        'success_rate' => 'decimal:2',
        'failure_rate' => 'decimal:2',
    ];

    /**
     * Get the notification type name.
     */
    public function getNotificationTypeNameAttribute(): string
    {
        return config("notices.notification_types.{$this->notification_type_id}", 'Unknown');
    }

    /**
     * Get the delivery method name.
     */
    public function getDeliveryMethodNameAttribute(): string
    {
        return config("notices.delivery_options.{$this->delivery_option_id}", 'Unknown');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('summary_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by notification type.
     */
    public function scopeOfType(Builder $query, int $typeId): Builder
    {
        return $query->where('notification_type_id', $typeId);
    }

    /**
     * Scope to filter by delivery method.
     */
    public function scopeByDeliveryMethod(Builder $query, int $deliveryId): Builder
    {
        return $query->where('delivery_option_id', $deliveryId);
    }

    /**
     * Get summary for a specific date.
     */
    public static function forDate(Carbon $date): Builder
    {
        return static::whereDate('summary_date', $date->format('Y-m-d'));
    }

    /**
     * Get aggregated totals for a date range.
     */
    public static function getAggregatedTotals(Carbon $startDate, Carbon $endDate): array
    {
        return static::dateRange($startDate, $endDate)
            ->selectRaw('
                SUM(total_sent) as total_sent,
                SUM(total_success) as total_success,
                SUM(total_failed) as total_failed,
                SUM(total_holds) as total_holds,
                SUM(total_overdues) as total_overdues,
                AVG(success_rate) as avg_success_rate
            ')
            ->first()
            ->toArray();
    }

    /**
     * Get summary breakdown by notification type.
     */
    public static function getBreakdownByType(Carbon $startDate, Carbon $endDate): array
    {
        return static::dateRange($startDate, $endDate)
            ->selectRaw('
                notification_type_id,
                SUM(total_sent) as total_sent,
                SUM(total_success) as total_success,
                SUM(total_failed) as total_failed
            ')
            ->groupBy('notification_type_id')
            ->get()
            ->toArray();
    }

    /**
     * Get summary breakdown by delivery method.
     */
    public static function getBreakdownByDelivery(Carbon $startDate, Carbon $endDate): array
    {
        return static::dateRange($startDate, $endDate)
            ->selectRaw('
                delivery_option_id,
                SUM(total_sent) as total_sent,
                SUM(total_success) as total_success,
                SUM(total_failed) as total_failed
            ')
            ->groupBy('delivery_option_id')
            ->get()
            ->toArray();
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return DailyNotificationSummaryFactory::new();
    }
}
