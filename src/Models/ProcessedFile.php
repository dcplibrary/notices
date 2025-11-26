<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedFile extends Model
{
    protected $fillable = [
        'filename',
        'file_type',
        'category',
        'status',
        'records_processed',
        'records_new',
        'records_updated',
        'records_unchanged',
        'records_errors',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'records_processed' => 'integer',
        'records_new' => 'integer',
        'records_updated' => 'integer',
        'records_unchanged' => 'integer',
        'records_errors' => 'integer',
    ];

    /**
     * Scope to get recently processed files.
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('processed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope by file type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('file_type', $type);
    }

    /**
     * Scope by category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if a file has been processed.
     */
    public static function isProcessed(string $filename, string $type): bool
    {
        return static::where('filename', $filename)
            ->where('file_type', $type)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get processing summary for a date range.
     */
    public static function getSummary($startDate = null, $endDate = null): array
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate]);
        }

        return [
            'total_files' => $query->count(),
            'by_type' => $query->clone()
                ->selectRaw('file_type, COUNT(*) as count, SUM(records_processed) as total_records')
                ->groupBy('file_type')
                ->get()
                ->mapWithKeys(fn ($item) => [
                    $item->file_type => [
                        'files' => $item->count,
                        'records' => $item->total_records,
                    ],
                ])
                ->toArray(),
            'by_status' => $query->clone()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }
}
