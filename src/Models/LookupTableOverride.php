<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lookup Table Override Model.
 *
 * Allows customization of lookup table display labels and visibility
 * without modifying the original Polaris data.
 *
 * Use cases:
 * - Override confusing Polaris labels with clearer descriptions
 * - Disable deprecated options from appearing in UI dropdowns
 * - Add contextual notes about why certain options exist
 *
 * Example:
 * LookupTableOverride::create([
 *     'table_name' => 'notification_types',
 *     'record_id' => 8,
 *     'custom_label' => '2nd Overdue (7 days)',
 *     'is_enabled' => true,
 *     'notes' => 'Polaris calls this "Fine" but it\'s actually 2nd overdue notice'
 * ]);
 */
class LookupTableOverride extends Model
{
    protected $fillable = [
        'table_name',
        'record_id',
        'custom_label',
        'is_enabled',
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the display label for a lookup table record.
     *
     * Returns custom_label if set, otherwise falls back to original label.
     */
    public static function getLabel(string $tableName, int $recordId, string $originalLabel): string
    {
        $override = static::where('table_name', $tableName)
            ->where('record_id', $recordId)
            ->first()
        ;

        return $override?->custom_label ?? $originalLabel;
    }

    /**
     * Check if a lookup table record is enabled for UI selection.
     */
    public static function isEnabled(string $tableName, int $recordId): bool
    {
        $override = static::where('table_name', $tableName)
            ->where('record_id', $recordId)
            ->first()
        ;

        // If no override exists, default to enabled
        return $override?->is_enabled ?? true;
    }

    /**
     * Get all enabled record IDs for a lookup table.
     *
     * Useful for filtering dropdown options.
     *
     * @param array $allRecordIds All possible IDs from the lookup table
     *
     * @return array Filtered array of enabled IDs
     */
    public static function getEnabledIds(string $tableName, array $allRecordIds): array
    {
        // Get explicitly disabled overrides
        $disabledIds = static::where('table_name', $tableName)
            ->where('is_enabled', false)
            ->pluck('record_id')
            ->toArray()
        ;

        // Return all IDs except disabled ones
        return array_diff($allRecordIds, $disabledIds);
    }
}
