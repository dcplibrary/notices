<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Notification Status Model
 *
 * Shared lookup table for notification statuses (sent, failed, bounced, etc.)
 * with customizable labels and UI styling.
 *
 * @property int $id
 * @property string $code
 * @property string $default_label
 * @property string|null $custom_label
 * @property string|null $description
 * @property string|null $color
 * @property string|null $icon
 * @property bool $is_success
 * @property bool $is_active
 * @property int $display_order
 * @property array|null $metadata
 * @property string $display_label (computed)
 */
class NotificationStatus extends Model
{
    protected $fillable = [
        'code',
        'default_label',
        'custom_label',
        'description',
        'color',
        'icon',
        'is_success',
        'is_active',
        'display_order',
        'metadata',
    ];

    protected $casts = [
        'is_success' => 'boolean',
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
     * Scope to filter active statuses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter success statuses
     */
    public function scopeSuccess($query)
    {
        return $query->where('is_success', true);
    }

    /**
     * Scope to filter failure statuses
     */
    public function scopeFailure($query)
    {
        return $query->where('is_success', false);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('default_label');
    }

    /**
     * Get notification status by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Get statuses as key-value pairs for dropdowns
     */
    public static function getOptions(?bool $successOnly = null): array
    {
        $query = static::active()->ordered();

        if ($successOnly !== null) {
            $query->where('is_success', $successOnly);
        }

        return $query->get()
            ->pluck('display_label', 'id')
            ->toArray();
    }

    /**
     * Get Tailwind badge classes based on status
     */
    public function getBadgeClasses(): string
    {
        if ($this->color) {
            return $this->color;
        }

        // Default badge classes using monochromatic scheme
        return $this->is_success
            ? 'bg-gray-100 text-gray-800 border-gray-300'
            : 'bg-gray-50 text-gray-600 border-gray-200';
    }
}
