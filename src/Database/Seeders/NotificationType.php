<?php

namespace Dcplibrary\Notices\Database\Seeders;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Notification Type Model
 *
 * Shared lookup table for notification types (overdue, holds, bills, etc.)
 * with customizable labels.
 *
 * @property int $id
 * @property string $code
 * @property string $default_label
 * @property string|null $custom_label
 * @property string|null $description
 * @property string|null $category
 * @property bool $is_active
 * @property int $display_order
 * @property array|null $metadata
 * @property string $display_label (computed)
 */
class NotificationType extends Model
{
    protected $fillable = [
        'code',
        'default_label',
        'custom_label',
        'description',
        'category',
        'is_active',
        'display_order',
        'metadata',
    ];

    protected $casts = [
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
     * Scope to filter active types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('default_label');
    }

    /**
     * Get notification type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Get types as key-value pairs for dropdowns
     */
    public static function getOptions(?string $category = null): array
    {
        $query = static::active()->ordered();

        if ($category) {
            $query->inCategory($category);
        }

        return $query->get()
            ->pluck('display_label', 'id')
            ->toArray();
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        return static::active()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->toArray();
    }
}
