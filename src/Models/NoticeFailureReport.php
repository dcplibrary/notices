<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

/**
 * Model for Shoutbomb failure reports.
 * Stores failure data from Shoutbomb report emails (opted-out, invalid, undelivered).
 *
 * This was migrated from dcplibrary/shoutbomb-reports package to consolidate
 * all notice-related data models in one place.
 */
class NoticeFailureReport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'outlook_message_id',
        'subject',
        'from_address',
        'patron_phone',
        'patron_id',
        'patron_barcode',
        'barcode_partial',
        'patron_name',
        'notice_type',
        'failure_type',
        'failure_reason',
        'account_status',
        'notice_description',
        'attempt_count',
        'received_at',
        'processed_at',
        'raw_content',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempt_count' => 'integer',
        'barcode_partial' => 'boolean',
    ];

    /**
     * Get the table name from config.
     * Supports both notices and shoutbomb-reports config keys for backwards compatibility.
     */
    public function getTable()
    {
        // Prefer our own integration config, fallback to the shoutbomb-reports package's config
        return Config::get('notices.integrations.shoutbomb_reports.table',
            Config::get('shoutbomb-reports.storage.table_name', 'notice_failure_reports'));
    }

    /**
     * Scope to get unprocessed reports.
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope to get reports by notice type (e.g., 'SMS' or 'Voice').
     */
    public function scopeByNoticeType(Builder $query, string $type): Builder
    {
        return $query->where('notice_type', $type);
    }

    /**
     * Scope by notice type (alias for consistency).
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('notice_type', $type);
    }

    /**
     * Scope to get reports by failure type.
     */
    public function scopeByFailureType(Builder $query, string $type): Builder
    {
        return $query->where('failure_type', $type);
    }

    /**
     * Scope to get opted-out patrons.
     */
    public function scopeOptedOut(Builder $query): Builder
    {
        return $query->where('failure_type', 'opted-out');
    }

    /**
     * Scope to get invalid phone numbers.
     */
    public function scopeInvalid(Builder $query): Builder
    {
        return $query->where('failure_type', 'invalid');
    }

    /**
     * Scope to get reports for a specific patron.
     */
    public function scopeForPatron(Builder $query, string $patronIdOrPhone): Builder
    {
        return $query->where(function ($q) use ($patronIdOrPhone) {
            $q->where('patron_id', $patronIdOrPhone)
              ->orWhere('patron_phone', $patronIdOrPhone)
              ->orWhere('patron_barcode', $patronIdOrPhone);
        });
    }

    /**
     * Scope by normalized phone (digits only compare on last 10).
     */
    public function scopeForPhone(Builder $query, string $phone): Builder
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        // Compare against the rightmost 10 digits of stored phone
        return $query->whereRaw('RIGHT(REGEXP_REPLACE(patron_phone, "[^0-9]", ""), 10) = ?', [substr($digits, -10)]);
    }

    /**
     * Scope to get recent reports.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('received_at', '>=', now()->subDays($days));
    }

    /**
     * Scope by date window around a target.
     */
    public function scopeAround(Builder $query, Carbon $center, int $hours = 24): Builder
    {
        return $query->whereBetween('received_at', [
            $center->copy()->subHours($hours),
            $center->copy()->addHours($hours),
        ]);
    }

    /**
     * Scope to get reports with partial barcodes only.
     */
    public function scopePartialBarcodes(Builder $query): Builder
    {
        return $query->where('barcode_partial', true);
    }

    /**
     * Scope to get invalid/removed barcodes.
     */
    public function scopeInvalidBarcodes(Builder $query): Builder
    {
        return $query->where('failure_type', 'invalid-barcode-removed');
    }

    /**
     * Scope to get deleted/unavailable accounts.
     */
    public function scopeUnavailableAccounts(Builder $query): Builder
    {
        return $query->whereIn('account_status', ['deleted', 'unavailable']);
    }

    /**
     * Scope to get deleted accounts.
     */
    public function scopeDeletedAccounts(Builder $query): Builder
    {
        return $query->where('account_status', 'deleted');
    }

    /**
     * Mark this report as processed.
     */
    public function markAsProcessed(): bool
    {
        $this->processed_at = now();
        return $this->save();
    }

    /**
     * Check if this is an opted-out failure.
     */
    public function isOptedOut(): bool
    {
        return $this->failure_type === 'opted-out';
    }

    /**
     * Check if this is an invalid phone number.
     */
    public function isInvalid(): bool
    {
        return $this->failure_type === 'invalid';
    }

    /**
     * Check if this has a partial (redacted) barcode.
     */
    public function hasPartialBarcode(): bool
    {
        return $this->barcode_partial === true;
    }

    /**
     * Check if account is deleted.
     */
    public function isAccountDeleted(): bool
    {
        return $this->account_status === 'deleted';
    }

    /**
     * Check if account is unavailable.
     */
    public function isAccountUnavailable(): bool
    {
        return in_array($this->account_status, ['deleted', 'unavailable']);
    }
}
