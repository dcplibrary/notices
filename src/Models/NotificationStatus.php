<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Polaris notification_statuses lookup table.
 *
 * Maps Polaris NotificationStatusID values to human-readable descriptions
 * (e.g. "Sent", "Cancelled", "Failed"). Labels can be overridden per-installation
 * via the lookup_table_overrides table; the `display_label` appended attribute
 * always reflects the active label.
 *
 * @property int    $id            Polaris NotificationStatusID
 * @property string $description   Default status description from Polaris
 * @property string $display_label Resolved label respecting lookup_table_overrides (appended)
 */
class NotificationStatus extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'description'];

    protected $casts = [
        'id' => 'integer',
    ];

    protected $appends = ['display_label'];

    public function deliveryConfirmations(): HasMany
    {
        return $this->hasMany(DeliveryConfirmation::class, 'notification_status_id');
    }

    /**
     * Get the display label with custom override applied.
     *
     * Returns custom_label from lookup_table_overrides if exists,
     * otherwise returns the original description value.
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::get(function () {
            $override = LookupTableOverride::where('table_name', 'notification_statuses')
                ->where('record_id', $this->id)
                ->first()
            ;

            return $override?->custom_label ?? $this->description;
        });
    }
}
