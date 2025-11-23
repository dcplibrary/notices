<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivery Option Model
 *
 * Shared lookup table for delivery options (Mail, Email, Voice, Text)
 * with customizable labels.
 *
 * @property int $id
 * @property int $polaris_id
 * @property string $default_label
 * @property string|null $custom_label
 * @property string|null $description
 * @property bool $is_active
 * @property int $display_order
 * @property array|null $metadata
 * @property string $display_label (computed)
 */
class DeliveryOption extends Model
{
    protected $fillable = [
        'polaris_id',
        'default_label',
        'custom_label',
        'description',
        'is_active',
        'display_order',
        'metadata',
    ];

    protected $casts = [
        'polaris_id' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the display label (custom if set, otherwise default)
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->custom_label ?? $this->default_label,
        );
    }

    /**
     * Scope to filter active options
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('default_label');
    }

    /**
     * Get delivery option by Polaris ID
     */
    public static function findByPolarisId(int $polarisId): ?self
    {
        return static::where('polaris_id', $polarisId)->first();
    }

    /**
     * Get options as key-value pairs for dropdowns
     */
    public static function getOptions(): array
    {
        return static::active()
            ->ordered()
            ->get()
            ->pluck('display_label', 'id')
            ->toArray();
    }
}
