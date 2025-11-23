<?php

namespace Dcplibrary\Notices\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoutbombKeywordUsage extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     */
    protected $table = 'shoutbomb_keyword_usage';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'keyword',
        'patron_barcode',
        'phone_number',
        'usage_date',
        'keyword_description',
        'usage_count',
        'report_file',
        'report_period',
        'imported_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'usage_date' => 'datetime',
        'imported_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('usage_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by keyword.
     */
    public function scopeByKeyword(Builder $query, string $keyword): Builder
    {
        return $query->where('keyword', strtoupper($keyword));
    }

    /**
     * Scope to filter by patron barcode.
     */
    public function scopeForPatron(Builder $query, string $barcode): Builder
    {
        return $query->where('patron_barcode', $barcode);
    }

    /**
     * Scope to get recent keyword usage.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('usage_date', '>=', now()->subDays($days));
    }

    /**
     * Get total usage count for a period.
     */
    public static function getTotalUsageByKeyword(string $keyword, Carbon $startDate = null, Carbon $endDate = null): int
    {
        $query = static::byKeyword($keyword);

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->sum('usage_count');
    }

    /**
     * Get keyword usage statistics.
     */
    public static function getKeywordStats(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query
            ->selectRaw('keyword, SUM(usage_count) as total_usage, COUNT(DISTINCT patron_barcode) as unique_patrons')
            ->groupBy('keyword')
            ->orderByDesc('total_usage')
            ->get()
            ->toArray();
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return \Dcplibrary\Notices\Database\Factories\ShoutbombKeywordUsageFactory::new();
    }
}
