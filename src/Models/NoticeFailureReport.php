<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

/**
 * Lightweight model to read failure rows written by dcplibrary/shoutbomb-reports.
 * Kept internal so notices can optionally consume the data without a hard
 * composer dependency on that package's PHP classes.
 */
class NoticeFailureReport extends Model
{
    /**
     * The table name is configurable to mirror the external package config.
     */
    public function getTable()
    {
        // Prefer our own integration config, fallback to the package's default key
        return Config::get('notices.integrations.shoutbomb_reports.table',
            Config::get('shoutbomb-reports.storage.table_name', 'notice_failure_reports'));
    }

    protected $guarded = [];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Scope by notice type (e.g., 'SMS' or 'Voice').
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('notice_type', $type);
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
     * Scope by date window around a target.
     */
    public function scopeAround(Builder $query, Carbon $center, int $hours = 24): Builder
    {
        return $query->whereBetween('received_at', [
            $center->copy()->subHours($hours),
            $center->copy()->addHours($hours),
        ]);
    }
}