<?php

namespace Dcplibrary\Notices\Support;

use Carbon\Carbon;

/**
 * Utility for consistent date formatting across the application.
 *
 * This class centralizes date formatting to ensure consistent,
 * user-friendly date display throughout the UI. Provides commonly
 * used date formats that are repeated throughout the codebase.
 *
 * ## Purpose
 *
 * Before this refactoring, date format strings were duplicated throughout
 * the codebase (35+ occurrences). This class provides a single source of
 * truth for date formatting.
 *
 * ## Usage Example
 *
 * ```php
 * // Format a date
 * $date = Carbon::parse('2026-01-15');
 * echo DateFormatter::formatDate($date);        // "Jan 15, 2026"
 * echo DateFormatter::formatDateTime($date);    // "Jan 15, 2026 2:00 PM"
 * ```
 *
 * @author DCPL Development Team
 *
 * @since 1.0.0
 */
class DateFormatter
{
    /**
     * Standard date format used throughout the application.
     *
     * Format: "M j, Y" (e.g., "Jan 15, 2026")
     */
    public const DATE_FORMAT = 'M j, Y';

    /**
     * Standard datetime format used throughout the application.
     *
     * Format: "M j, Y g:i A" (e.g., "Jan 15, 2026 2:00 PM")
     */
    public const DATETIME_FORMAT = 'M j, Y g:i A';

    /**
     * Format a date in the standard application format.
     *
     * Formats a date as "M j, Y" (e.g., "Jan 15, 2026").
     * Returns null if the input date is null.
     *
     * @param null|Carbon|\DateTimeInterface $date Date to format
     *
     * @return null|string Formatted date string, or null if input is null
     *
     * @example
     * ```php
     * DateFormatter::formatDate(Carbon::parse('2026-01-15'));  // "Jan 15, 2026"
     * DateFormatter::formatDate(null);                          // null
     * ```
     */
    public static function formatDate(?\DateTimeInterface $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return Carbon::instance($date)->format(self::DATE_FORMAT);
    }

    /**
     * Format a datetime in the standard application format.
     *
     * Formats a datetime as "M j, Y g:i A" (e.g., "Jan 15, 2026 2:00 PM").
     * Returns null if the input date is null.
     *
     * @param null|Carbon|\DateTimeInterface $date Date to format
     *
     * @return null|string Formatted datetime string, or null if input is null
     *
     * @example
     * ```php
     * DateFormatter::formatDateTime(Carbon::parse('2026-01-15 14:30:00'));  // "Jan 15, 2026 2:30 PM"
     * DateFormatter::formatDateTime(null);                                   // null
     * ```
     */
    public static function formatDateTime(?\DateTimeInterface $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return Carbon::instance($date)->format(self::DATETIME_FORMAT);
    }
}
