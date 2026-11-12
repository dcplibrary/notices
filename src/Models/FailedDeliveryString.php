<?php

namespace Dcplibrary\Notices\Models;

use Dcplibrary\Notices\Models\Concerns\HasUtcTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tracks delivery strings (email addresses, phone numbers) that have failed.
 *
 * When a delivery string fails, ALL patrons using it are potentially affected.
 */
class FailedDeliveryString extends Model
{
    use HasUtcTimestamps;

    protected $table = 'failed_delivery_strings';

    protected $guarded = [];

    protected $casts = [
        'delivery_option_id'    => 'integer',
        'failure_count'         => 'integer',
        'is_resolved'           => 'boolean',
        'affected_patron_count' => 'integer',
        'resolved_by_user_id'   => 'integer',
    ];

    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class, 'delivery_option_id');
    }

    public function patronIssues(): HasMany
    {
        return $this->hasMany(PatronDeliveryIssue::class, 'failed_delivery_string_id');
    }

    protected function firstFailedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function lastFailedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function resolvedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }
}
