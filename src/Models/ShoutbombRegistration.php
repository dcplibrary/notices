<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoutbombRegistration extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     */
    protected $table = 'shoutbomb_registrations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'snapshot_date',
        'total_text_subscribers',
        'total_voice_subscribers',
        'total_subscribers',
        'text_percentage',
        'voice_percentage',
        'text_change',
        'voice_change',
        'new_registrations',
        'unsubscribes',
        'invalid_numbers',
        'report_file',
        'report_type',
        'imported_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'snapshot_date' => 'date',
        'imported_at' => 'datetime',
        'total_text_subscribers' => 'integer',
        'total_voice_subscribers' => 'integer',
        'total_subscribers' => 'integer',
        'text_percentage' => 'decimal:2',
        'voice_percentage' => 'decimal:2',
        'text_change' => 'integer',
        'voice_change' => 'integer',
        'new_registrations' => 'integer',
        'unsubscribes' => 'integer',
        'invalid_numbers' => 'integer',
    ];

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('snapshot_date', [$startDate, $endDate]);
    }

    /**
     * Get the latest registration snapshot.
     */
    public static function latest(): ?self
    {
        return static::orderByDesc('snapshot_date')->first();
    }

    /**
     * Get registration trend data.
     */
    public static function getTrend(int $days = 30): array
    {
        return static::query()
            ->where('snapshot_date', '>=', now()->subDays($days))
            ->orderBy('snapshot_date')
            ->get()
            ->map(function ($snapshot) {
                return [
                    'date' => $snapshot->snapshot_date->format('Y-m-d'),
                    'text' => $snapshot->total_text_subscribers,
                    'voice' => $snapshot->total_voice_subscribers,
                    'total' => $snapshot->total_subscribers,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate growth rate from previous snapshot.
     */
    public function getGrowthRateAttribute(): ?float
    {
        $previous = static::where('snapshot_date', '<', $this->snapshot_date)
            ->orderByDesc('snapshot_date')
            ->first();

        if (!$previous || $previous->total_subscribers == 0) {
            return null;
        }

        return (($this->total_subscribers - $previous->total_subscribers) / $previous->total_subscribers) * 100;
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return \Dcplibrary\Notices\Database\Factories\ShoutbombRegistrationFactory::new();
    }
}
