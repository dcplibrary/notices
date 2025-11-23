<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\NotificationSetting;
use Dcplibrary\Notices\Models\PatronPreference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SettingsManager
{
    protected int $cacheTtl = 3600; // 1 hour

    /**
     * Get a setting value with fallback to config files.
     *
     * @param string $key Full key in format "group.key" (e.g., "shoutbomb.ftp.host")
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // Try cache first
        $cacheKey = "notification_setting:global:{$key}";

        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($key, $default) {
            // Parse key
            [$group, $settingKey] = $this->parseKey($key);

            // Try database first
            $setting = NotificationSetting::global()
                ->where('group', $group)
                ->where('key', $settingKey)
                ->first();

            if ($setting) {
                return $setting->getTypedValue();
            }

            // Fallback to config file (package config lives under 'notices')
            return Config::get("notices.{$key}", $default);
        });
    }

    /**
     * Get scoped settings (e.g., per-branch or per-channel).
     *
     * @param string $scope Scope type (branch, channel, etc.)
     * @param string $scopeId Scope identifier
     * @param string|null $key Specific key or null for all
     * @return mixed
     */
    public function getScoped(string $scope, string $scopeId, ?string $key = null)
    {
        $cacheKey = "notification_setting:{$scope}:{$scopeId}:" . ($key ?? 'all');

        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($scope, $scopeId, $key) {
            $query = NotificationSetting::forScope($scope, $scopeId);

            if ($key) {
                [$group, $settingKey] = $this->parseKey($key);
                $setting = $query->where('group', $group)
                    ->where('key', $settingKey)
                    ->first();

                return $setting ? $setting->getTypedValue() : null;
            }

            // Return all settings for this scope
            return $query->get()
                ->mapWithKeys(function($setting) {
                    return [$setting->full_key => $setting->getTypedValue()];
                })
                ->toArray();
        });
    }

    /**
     * Get all settings in a group.
     *
     * @param string $group Setting group name
     * @return array
     */
    public function getGroup(string $group): array
    {
        $cacheKey = "notification_setting:group:{$group}";

        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($group) {
            return NotificationSetting::global()
                ->inGroup($group)
                ->get()
                ->mapWithKeys(function($setting) {
                    return [$setting->key => $setting->getTypedValue()];
                })
                ->toArray();
        });
    }

    /**
     * Set a setting value.
     *
     * @param string $key Full key in format "group.key"
     * @param mixed $value Value to set
     * @param string|null $scope Optional scope
     * @param string|null $scopeId Optional scope ID
     * @param string|null $updatedBy Who is updating
     * @return NotificationSetting
     */
    public function set(string $key, $value, ?string $scope = null, ?string $scopeId = null, ?string $updatedBy = null): NotificationSetting
    {
        [$group, $settingKey] = $this->parseKey($key);

        $setting = NotificationSetting::updateOrCreate(
            [
                'scope' => $scope,
                'scope_id' => $scopeId,
                'group' => $group,
                'key' => $settingKey,
            ],
            [
                'updated_by' => $updatedBy,
            ]
        );

        $setting->setTypedValue($value);
        $setting->save();

        // Clear cache
        $this->clearCache($scope, $scopeId, $key);

        return $setting;
    }

    /**
     * Delete a setting.
     *
     * @param string $key
     * @param string|null $scope
     * @param string|null $scopeId
     * @return bool
     */
    public function delete(string $key, ?string $scope = null, ?string $scopeId = null): bool
    {
        [$group, $settingKey] = $this->parseKey($key);

        $deleted = NotificationSetting::where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('group', $group)
            ->where('key', $settingKey)
            ->delete();

        if ($deleted) {
            $this->clearCache($scope, $scopeId, $key);
        }

        return $deleted > 0;
    }

    /**
     * Get patron preferences.
     *
     * @param string $patronBarcode
     * @return PatronPreference|null
     */
    public function getPatronPreferences(string $patronBarcode): ?PatronPreference
    {
        $cacheKey = "patron_preferences:{$patronBarcode}";

        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($patronBarcode) {
            return PatronPreference::where('patron_barcode', $patronBarcode)->first();
        });
    }

    /**
     * Update patron preferences.
     *
     * @param string $patronBarcode
     * @param array $preferences
     * @param string $updatedBy
     * @param string $source
     * @return PatronPreference
     */
    public function updatePatronPreferences(string $patronBarcode, array $preferences, string $updatedBy = 'system', string $source = 'api'): PatronPreference
    {
        $patron = PatronPreference::firstOrNew(['patron_barcode' => $patronBarcode]);
        $patron->updatePreferences($preferences, $updatedBy, $source);

        // Clear cache
        Cache::forget("patron_preferences:{$patronBarcode}");

        return $patron;
    }

    /**
     * Check if patron can receive notification.
     *
     * @param string $patronBarcode
     * @param string $channel
     * @param string $type
     * @return bool
     */
    public function canPatronReceive(string $patronBarcode, string $channel, string $type): bool
    {
        $preferences = $this->getPatronPreferences($patronBarcode);

        if (!$preferences) {
            return true; // No preferences = allow all
        }

        return $preferences->canReceiveNotification($channel, $type);
    }

    /**
     * Get all editable settings grouped by group.
     *
     * @return array
     */
    public function getEditableSettings(): array
    {
        return Cache::remember('notification_settings:editable', $this->cacheTtl, function() {
            return NotificationSetting::global()
                ->editable()
                ->orderBy('group')
                ->orderBy('key')
                ->get()
                ->groupBy('group')
                ->toArray();
        });
    }

    /**
     * Parse a key into group and setting name.
     *
     * @param string $key
     * @return array [group, key]
     */
    protected function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        if (count($parts) === 1) {
            return ['general', $parts[0]];
        }

        return $parts;
    }

    /**
     * Clear cache for a setting.
     *
     * @param string|null $scope
     * @param string|null $scopeId
     * @param string|null $key
     * @return void
     */
    protected function clearCache(?string $scope, ?string $scopeId, ?string $key): void
    {
        if (!$scope) {
            Cache::forget("notification_setting:global:{$key}");
            Cache::forget('notification_settings:editable');

            if ($key) {
                [$group, $_] = $this->parseKey($key);
                Cache::forget("notification_setting:group:{$group}");
            }
        } else {
            Cache::forget("notification_setting:{$scope}:{$scopeId}:" . ($key ?? 'all'));
            Cache::forget("notification_setting:{$scope}:{$scopeId}:all");
        }
    }

    /**
     * Clear all settings cache.
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        Cache::flush(); // Nuclear option - consider more targeted approach in production
    }
}
