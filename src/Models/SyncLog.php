<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_type',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'results',
        'error_message',
        'records_processed',
        'user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'results' => 'array',
    ];

    /**
     * Get the latest log for a specific operation type.
     */
    public static function latestFor(string $operationType): ?self
    {
        return static::where('operation_type', $operationType)
            ->latest('started_at')
            ->first();
    }

    /**
     * Get recent logs for display.
     */
    public static function recent(int $limit = 10)
    {
        return static::latest('started_at')->limit($limit)->get();
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(array $results = [], int $recordsProcessed = 0): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
            'results' => $results,
            'records_processed' => $recordsProcessed,
        ]);
    }

    /**
     * Mark as completed with errors.
     */
    public function markCompletedWithErrors(array $results = [], string $errorMessage = ''): void
    {
        $this->update([
            'status' => 'completed_with_errors',
            'completed_at' => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
            'results' => $results,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
            'error_message' => $errorMessage,
        ]);
    }
}
