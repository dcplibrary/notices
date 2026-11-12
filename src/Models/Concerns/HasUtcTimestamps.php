<?php

namespace Dcplibrary\Notices\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Trait for models that store timestamps in UTC and need automatic conversion
 * to the application timezone.
 *
 * Usage: Add `use HasUtcTimestamps;` to your model class.
 *
 * This trait provides automatic timezone conversion for:
 * - created_at
 * - updated_at
 * - deleted_at (for soft deletes)
 *
 * The database stores all timestamps in UTC, and this trait automatically
 * converts them to the application timezone (configured in APP_TIMEZONE)
 * when accessed, and converts back to UTC when saving.
 */
trait HasUtcTimestamps
{
    /**
     * Get the created_at attribute with timezone conversion.
     */
    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->setTimezone('UTC')->format('Y-m-d H:i:s') : null,
        );
    }

    /**
     * Get the updated_at attribute with timezone conversion.
     */
    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->setTimezone('UTC')->format('Y-m-d H:i:s') : null,
        );
    }

    /**
     * Get the deleted_at attribute with timezone conversion (for soft deletes).
     */
    protected function deletedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->setTimezone('UTC')->format('Y-m-d H:i:s') : null,
        );
    }

    /**
     * Helper method to create a timezone-aware attribute accessor/mutator.
     *
     * Use this for custom datetime fields in your model:
     *
     * protected function myCustomDate(): Attribute
     * {
     *     return $this->utcTimestampAttribute();
     * }
     */
    protected function utcTimestampAttribute(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')) : null,
            set: function ($value) {
                // Don't parse DB raw expressions - pass them through as-is
                if ($value instanceof \Illuminate\Contracts\Database\Query\Expression) {
                    return $value;
                }

                return $value ? Carbon::parse($value)->setTimezone('UTC')->format('Y-m-d H:i:s') : null;
            },
        );
    }
}
