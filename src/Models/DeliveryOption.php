<?php

namespace Dcplibrary\Notices\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Polaris delivery_options lookup model.
 *
 * Mirrors Polaris DeliveryOptions lookup table, providing the human-readable
 * delivery_option label for a given DeliveryOptionID.
 */
class DeliveryOption extends Model
{
    /**
     * Icon name constants for delivery methods.
     * These are semantic names, not hardcoded IDs.
     */
    public const ICON_MAIL    = 'mail';
    public const ICON_EMAIL   = 'email';
    public const ICON_PHONE   = 'phone';
    public const ICON_SMS     = 'sms';
    public const ICON_UNKNOWN = 'unknown';
    public $incrementing      = false;
    protected $fillable       = ['id', 'delivery_option'];

    protected $casts = [
        'id' => 'integer',
    ];

    protected $appends = ['display_label', 'icon_name'];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'delivery_option_id');
    }

    public function patrons(): HasMany
    {
        return $this->hasMany(Patron::class, 'delivery_option_id');
    }

    /**
     * Check if this delivery option uses LX Starter for electronic delivery.
     *
     * Reads configuration from lookup_table_overrides.notes JSON field.
     * Defaults to true (assumes LX Starter) unless explicitly set to false.
     *
     * @return bool True if this delivery method uses LX Starter
     */
    public function usesLxStarter(): bool
    {
        $override = LookupTableOverride::where('table_name', 'delivery_options')
            ->where('record_id', $this->id)
            ->first()
        ;

        if (!$override || !$override->notes) {
            // Default: assume electronic delivery via LX Starter
            return true;
        }

        $config = json_decode($override->notes, true);

        // Check for uses_lx_starter flag in config
        return $config['uses_lx_starter'] ?? true;
    }

    /**
     * Check if this delivery option is enabled for UI display.
     *
     * Reads is_enabled flag from lookup_table_overrides.
     * Defaults to true (enabled) unless explicitly disabled.
     *
     * @return bool True if this delivery option should be shown in UI
     */
    public function isEnabled(): bool
    {
        return LookupTableOverride::isEnabled('delivery_options', $this->id);
    }

    /**
     * Get delivery option ID from method string by querying the database.
     *
     * Matches against both the original delivery_option field and display_label
     * (which includes custom overrides). Only returns IDs for enabled options.
     *
     * The database is the single source of truth - no hardcoded mappings.
     *
     * @param string $method Method name (e.g., "email", "voice", "sms", "Mailing Address")
     *
     * @return null|int Delivery option ID or null if not found or disabled
     */
    public static function getIdFromMethod(string $method): ?int
    {
        if (empty(trim($method))) {
            return null;
        }

        $normalized = strtolower(trim($method));

        // Get all enabled delivery options from database
        $enabledOptions = static::getEnabled();

        // Try to match against delivery_option field (original Polaris name)
        foreach ($enabledOptions as $option) {
            $originalName = strtolower($option->delivery_option);
            $displayLabel = strtolower($option->display_label);

            // Exact match on original name
            if ($originalName === $normalized) {
                return $option->id;
            }

            // Exact match on display label (with custom override)
            if ($displayLabel === $normalized) {
                return $option->id;
            }

            // Partial match - check if normalized method is contained in original name or display label
            // e.g., "email" matches "Email Address", "voice" matches "Phone 1" (if custom label is "Voice")
            if (str_contains($originalName, $normalized) || str_contains($displayLabel, $normalized)) {
                return $option->id;
            }

            // Reverse partial match - check if original name/display label is contained in normalized method
            // e.g., "email address" matches "email"
            if (str_contains($normalized, $originalName) || str_contains($normalized, $displayLabel)) {
                return $option->id;
            }
        }

        // No match found
        return null;
    }

    /**
     * Get delivery option by method string.
     * Only returns enabled delivery options.
     *
     * @param string $method Method name
     */
    public static function findByMethod(string $method): ?self
    {
        $id = static::getIdFromMethod($method);

        return $id ? static::find($id) : null;
    }

    /**
     * Get all enabled delivery options.
     *
     * Filters out any delivery options that have is_enabled = false in lookup_table_overrides.
     */
    public static function getEnabled(): \Illuminate\Database\Eloquent\Collection
    {
        $allOptions = static::all();

        return $allOptions->filter(function ($option) {
            return $option->isEnabled();
        })->values();
    }

    /**
     * Get enabled delivery option IDs.
     *
     * @return array Array of enabled delivery_option_id values
     */
    public static function getEnabledIds(): array
    {
        $allIds = static::pluck('id')->toArray();

        return LookupTableOverride::getEnabledIds('delivery_options', $allIds);
    }

    /**
     * Get the display label with custom override applied.
     *
     * Returns custom_label from lookup_table_overrides if exists,
     * otherwise returns the original delivery_option value.
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::get(function () {
            $override = LookupTableOverride::where('table_name', 'delivery_options')
                ->where('record_id', $this->id)
                ->first()
            ;

            return $override?->custom_label ?? $this->delivery_option;
        });
    }

    /**
     * Get the semantic icon name for this delivery option.
     *
     * Determines the icon based on the delivery_option name from the database.
     * Returns one of the ICON_* constants which can be used by views to render
     * the appropriate icon. Supports custom icon override via lookup_table_overrides notes JSON.
     *
     * @return string One of: 'mail', 'email', 'phone', 'sms', 'unknown'
     */
    protected function iconName(): Attribute
    {
        return Attribute::get(function () {
            // Check for custom icon override in lookup_table_overrides notes
            $override = LookupTableOverride::where('table_name', 'delivery_options')
                ->where('record_id', $this->id)
                ->first()
            ;

            if ($override?->notes) {
                $config = json_decode($override->notes, true);
                if (!empty($config['icon'])) {
                    return $config['icon'];
                }
            }

            // Determine icon from delivery_option name (database value, not hardcoded ID)
            $name = strtolower($this->delivery_option ?? '');

            // Physical mail (envelope) - must check before 'mail' to avoid matching 'email'
            if (str_contains($name, 'mail') && !str_contains($name, 'email')) {
                return self::ICON_MAIL;
            }

            // Email (@ symbol)
            if (str_contains($name, 'email')) {
                return self::ICON_EMAIL;
            }

            // Voice/Phone
            if (str_contains($name, 'phone') || str_contains($name, 'voice')) {
                return self::ICON_PHONE;
            }

            // SMS/Text messaging
            if (str_contains($name, 'txt') || str_contains($name, 'sms') || str_contains($name, 'text')) {
                return self::ICON_SMS;
            }

            return self::ICON_UNKNOWN;
        });
    }
}
