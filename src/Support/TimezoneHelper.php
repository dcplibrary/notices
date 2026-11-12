<?php

namespace Dcplibrary\Notices\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Helper for handling timezone conversions between app timezone and UTC database storage.
 *
 * PROBLEM: The database stores timestamps in UTC, but Laravel converts them to the
 * app timezone (America/Chicago) when reading. This causes issues when querying
 * by hour or building time-based filters.
 *
 * EXAMPLE: A notification exported at 8:04 AM Chicago time on Jan 1, 2026
 * is stored as 2026-01-01 14:04:04 UTC in the database (CST = UTC-6).
 *
 * When filtering by "hour 8", we need to convert 8 AM Chicago → 14:00 UTC.
 *
 * This class centralizes all timezone conversion logic to prevent bugs.
 */
class TimezoneHelper
{
    /**
     * Convert app timezone date/hour to UTC start and end timestamps.
     *
     * Used for querying notifications within a specific hour in the app timezone.
     *
     * @param string $date Date in Y-m-d format (app timezone)
     * @param int    $hour Hour in 24-hour format 0-23 (app timezone)
     *
     * @return array [Carbon $start, Carbon $end] in UTC
     *
     * @example
     * ```php
     * // Get UTC range for 8 AM Chicago time on Jan 1, 2026
     * [$start, $end] = TimezoneHelper::getUtcRangeForHour('2026-01-01', 8);
     * // $start = 2026-01-01 14:00:00 UTC
     * // $end   = 2026-01-01 15:00:00 UTC
     * ```
     */
    public static function getUtcRangeForHour(string $date, int $hour): array
    {
        $start = Carbon::parse($date . ' ' . $hour . ':00:00', config('app.timezone'))->utc();
        $end   = $start->copy()->addHour();

        return [$start, $end];
    }

    /**
     * Get the UTC hour(s) corresponding to an app timezone hour.
     *
     * Returns an array of UTC hours that overlap with the given app timezone hour.
     * Usually returns 1 hour, but can return 2 if the hour spans midnight in UTC.
     *
     * @param int $hour Hour in 24-hour format 0-23 (app timezone)
     *
     * @return array Array of UTC hours that correspond to this app timezone hour
     *
     * @example
     * ```php
     * // Chicago CST (UTC-6): 8 AM Chicago = 14:00 UTC
     * TimezoneHelper::getUtcHoursForAppHour(8);
     * // Returns: [14]
     *
     * // If app timezone hour spans midnight in UTC:
     * TimezoneHelper::getUtcHoursForAppHour(19); // 7 PM Chicago
     * // Returns: [1] (next day in UTC)
     * ```
     */
    public static function getUtcHoursForAppHour(int $hour): array
    {
        // Use a dummy date to calculate the offset
        // Jan 1 to ensure we're in winter (CST) for consistent offset
        $start = Carbon::parse('2000-01-01 ' . $hour . ':00:00', config('app.timezone'))->utc();
        $end   = $start->copy()->addHour();

        $startHour = (int) $start->format('G');
        $endHour   = (int) $end->format('G');

        if ($startHour === $endHour || '00' === $end->format('i')) {
            // Same hour in UTC, or exactly on the hour boundary
            return [$startHour];
        }

        // Hour spans two UTC hours (crosses midnight)
        return [$startHour, $endHour % 24];
    }

    /**
     * Build a whereRaw condition for filtering by app timezone hour.
     *
     * Handles timezone conversion and midnight boundary crossing.
     *
     * NOTE: This now generates MySQL-compatible SQL using HOUR() instead of
     * SQLite's STRFTIME(). We assume the underlying timestamp is stored in UTC
     * and that getUtcHoursForAppHour() has already converted the app hour to
     * the corresponding UTC hour(s).
     */
    public static function buildHourWhereClause(string $column, int $hour): array
    {
        $utcHours = self::getUtcHoursForAppHour($hour);
        $driver   = DB::getDriverName();

        // Choose DB-specific hour extraction function
        $hourExpr = match ($driver) {
            'sqlite' => 'CAST(STRFTIME("%H", ' . $column . ') AS INTEGER)',
            'mysql'  => 'HOUR(' . $column . ')',
            default  => 'EXTRACT(HOUR FROM ' . $column . ')',
        };

        if (1 === count($utcHours)) {
            return [
                $hourExpr . ' = ?',
                [$utcHours[0]],
            ];
        }

        // Multiple hours (spans midnight)
        return [
            $hourExpr . ' IN (?, ?)',
            [$utcHours[0], $utcHours[1]],
        ];
    }
}
