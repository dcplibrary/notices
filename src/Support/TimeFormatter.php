<?php

namespace Dcplibrary\Notices\Support;

/**
 * Utility for consistent time formatting across the application.
 *
 * This class centralizes 12-hour time conversion and formatting to ensure
 * consistent, user-friendly time display throughout the UI. Library staff
 * in the US expect 12-hour time format with AM/PM indicators, not 24-hour
 * military time.
 *
 * ## Purpose
 *
 * Before this refactoring, time conversion logic was duplicated in multiple places:
 * - NotificationService::getOrphanedNotifications()
 * - NotificationService::getClusterInfoForNotification()
 * - Pattern analysis methods
 *
 * This class provides a single source of truth for time formatting.
 *
 * ## Usage Example
 *
 * ```php
 * // Convert hour from database (24-hour) to display format
 * $hour = 14; // 2 PM in 24-hour format
 * echo TimeFormatter::to12Hour($hour);      // "2:00 PM"
 * echo TimeFormatter::to12HourRange($hour); // "2:00-2:59 PM"
 * ```
 *
 * @author DCPL Development Team
 *
 * @since 1.0.0
 */
class TimeFormatter
{
    /**
     * Convert 24-hour time to 12-hour format with AM/PM.
     *
     * Converts a 24-hour format hour (0-23) to a user-friendly 12-hour
     * time string with AM/PM indicator.
     *
     * @param int $hour24 Hour in 24-hour format (0-23)
     *
     * @return string Formatted time like "2:00 PM" or "11:00 AM"
     *
     * @example
     * ```php
     * TimeFormatter::to12Hour(0);  // "12:00 AM"
     * TimeFormatter::to12Hour(12); // "12:00 PM"
     * TimeFormatter::to12Hour(14); // "2:00 PM"
     * TimeFormatter::to12Hour(23); // "11:00 PM"
     * ```
     */
    public static function to12Hour(int $hour24): string
    {
        $hour12 = $hour24 % 12 ?: 12;
        $ampm   = $hour24 < 12 ? 'AM' : 'PM';

        return sprintf('%d:00 %s', $hour12, $ampm);
    }

    /**
     * Convert 24-hour time to 12-hour range format.
     *
     * Creates a formatted time range string representing a full hour window.
     * This is commonly used for displaying failure clusters that occurred
     * during a specific hour.
     *
     * The range shows the full 60-minute window (e.g., 2:00 through 2:59).
     *
     * @param int $hour24 Hour in 24-hour format (0-23)
     *
     * @return string Formatted time range like "2:00-2:59 PM"
     *
     * @example
     * ```php
     * TimeFormatter::to12HourRange(0);  // "12:00-12:59 AM"
     * TimeFormatter::to12HourRange(14); // "2:00-2:59 PM"
     * TimeFormatter::to12HourRange(23); // "11:00-11:59 PM"
     * ```
     *
     * @used-in Orphaned notification cluster displays
     * @used-in Pattern analysis descriptions
     */
    public static function to12HourRange(int $hour24): string
    {
        $hour12 = $hour24 % 12 ?: 12;
        $ampm   = $hour24 < 12 ? 'AM' : 'PM';

        return sprintf('%d:00-%d:59 %s', $hour12, $hour12, $ampm);
    }

    /**
     * Get AM/PM indicator for a 24-hour time.
     *
     * Simple utility to determine morning (AM) vs afternoon/evening (PM).
     *
     * @param int $hour24 Hour in 24-hour format (0-23)
     *
     * @return string "AM" or "PM"
     *
     * @example
     * ```php
     * TimeFormatter::getAmPm(0);  // "AM"
     * TimeFormatter::getAmPm(11); // "AM"
     * TimeFormatter::getAmPm(12); // "PM"
     * TimeFormatter::getAmPm(23); // "PM"
     * ```
     */
    public static function getAmPm(int $hour24): string
    {
        return $hour24 < 12 ? 'AM' : 'PM';
    }

    /**
     * Convert 24-hour to 12-hour (number only, no AM/PM).
     *
     * Returns just the hour number in 12-hour format without AM/PM indicator.
     * Useful when you need to separately handle the hour number and period.
     *
     * Note: Hour 0 (midnight) returns 12, and hour 12 (noon) returns 12.
     *
     * @param int $hour24 Hour in 24-hour format (0-23)
     *
     * @return int Hour in 12-hour format (1-12)
     *
     * @example
     * ```php
     * TimeFormatter::get12HourValue(0);  // 12 (midnight)
     * TimeFormatter::get12HourValue(1);  // 1
     * TimeFormatter::get12HourValue(12); // 12 (noon)
     * TimeFormatter::get12HourValue(13); // 1
     * TimeFormatter::get12HourValue(23); // 11
     * ```
     */
    public static function get12HourValue(int $hour24): int
    {
        return $hour24 % 12 ?: 12;
    }
}
