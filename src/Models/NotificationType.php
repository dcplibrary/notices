<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Polaris notification_types lookup table.
 *
 * Maps Polaris NotificationTypeID values to human-readable descriptions
 * (e.g. "Hold Pickup", "1st Overdue", "Bill"). Labels can be overridden per-installation
 * via lookup_table_overrides; the `display_label` appended attribute always reflects
 * the active label.
 *
 * @property int    $id            Polaris NotificationTypeID
 * @property string $description   Default type description from Polaris
 * @property string $display_label Resolved label respecting lookup_table_overrides (appended)
 */
class NotificationType extends Model
{
    public $incrementing = false;
    protected $fillable  = ['id', 'description'];

    protected $casts = [
        'id' => 'integer',
    ];

    protected $appends = ['display_label'];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'notification_type_id');
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
            $override = LookupTableOverride::where('table_name', 'notification_types')
                ->where('record_id', $this->id)
                ->first()
            ;

            return $override?->custom_label ?? $this->description;
        });
    }
}
