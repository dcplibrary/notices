<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Email report type lookup table.
 *
 * Defines the different types of email reports received from Shoutbomb and Polaris:
 * - Shoutbomb failure reports (voice, SMS, invalid phones)
 * - Polaris summary reports (email/SMS delivery statistics)
 * - Other notifications
 */
class EmailReportType extends Model
{
    protected $table = 'email_report_types';

    protected $guarded = [];

    protected $casts = [
        'display_order' => 'integer',
    ];

    /**
     * Get ingested emails of this type.
     */
    public function ingestedEmails(): HasMany
    {
        return $this->hasMany(IngestedEmail::class, 'email_report_type_id');
    }

    /**
     * Get display label (respects lookup_table_overrides).
     */
    public function getDisplayLabelAttribute(): string
    {
        $override = LookupTableOverride::where('table_name', 'email_report_types')
            ->where('record_id', $this->id)
            ->first()
        ;

        return $override?->custom_label ?? $this->label;
    }

    /**
     * Check if this report type is enabled for display.
     */
    public function isEnabled(): bool
    {
        $override = LookupTableOverride::where('table_name', 'email_report_types')
            ->where('record_id', $this->id)
            ->first()
        ;

        return $override?->is_enabled ?? true;
    }

    /**
     * Get all enabled report types ordered by display_order.
     */
    public static function enabled()
    {
        return static::query()
            ->orderBy('display_order')
            ->orderBy('label')
            ->get()
            ->filter(fn ($type) => $type->isEnabled())
        ;
    }
}
