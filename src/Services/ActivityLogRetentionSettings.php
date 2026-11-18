<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\NoticesSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Saved activity-log retention choices from Settings → Maintenance → Cleanup.
 *
 * Days fall back to notices.activity_log.retention_days
 * (NOTICES_ACTIVITY_LOG_RETENTION_DAYS, default 90) until someone saves a value.
 * Keep-unresolved is off until it is turned on.
 */
class ActivityLogRetentionSettings
{
    public const DAYS_KEY = 'activity_log.retention_days';

    public const KEEP_UNRESOLVED_KEY = 'activity_log.keep_unresolved';

    public function days(): int
    {
        if (!$this->tableExists()) {
            return max(1, (int) config('notices.activity_log.retention_days', 90));
        }

        $stored = NoticesSetting::query()->where('key', self::DAYS_KEY)->value('value');
        $days   = null === $stored
            ? (int) config('notices.activity_log.retention_days', 90)
            : (int) $stored;

        return max(1, $days);
    }

    public function keepUnresolved(): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $stored = NoticesSetting::query()->where('key', self::KEEP_UNRESOLVED_KEY)->value('value');

        return null !== $stored && filter_var($stored, FILTER_VALIDATE_BOOLEAN);
    }

    public function save(int $days, bool $keepUnresolved): void
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('The notices_settings table does not exist. Run the Notices migrations before saving activity log retention.');
        }

        NoticesSetting::query()->updateOrCreate(
            ['key' => self::DAYS_KEY],
            ['value' => (string) max(1, $days)],
        );
        NoticesSetting::query()->updateOrCreate(
            ['key' => self::KEEP_UNRESOLVED_KEY],
            ['value' => $keepUnresolved ? '1' : '0'],
        );
    }

    protected function tableExists(): bool
    {
        return Schema::hasTable('notices_settings');
    }
}
