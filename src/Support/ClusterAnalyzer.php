<?php

namespace Dcplibrary\Notices\Support;

use Dcplibrary\Notices\Models\Notification;

/**
 * Utility for analyzing notification failure clusters.
 *
 * A "cluster" is a group of failed notifications that occurred during the same
 * hour window. Clusters help identify systemic vs random failures in the
 * Polaris → LX Starter notification pipeline.
 *
 * ## Cluster Types
 *
 * - **Complete Failure**: All notifications failed during that hour (suggests systemic issue)
 * - **Partial Failure**: Some failed, some succeeded (suggests patron-specific or random issues)
 *
 * ## Purpose
 *
 * Before this refactoring, cluster analysis logic was duplicated in:
 * - NotificationService::getOrphanedNotifications() (for dashboard display)
 * - NotificationService::getClusterInfoForNotification() (for issue details)
 *
 * This class provides a single source of truth for cluster analysis queries.
 *
 * ## Usage Example
 *
 * ```php
 * $date = '2025-01-01';
 * $hour = 14; // 2 PM
 *
 * $successCount = ClusterAnalyzer::countSuccessfulDeliveries($date, $hour);
 * $failedCount = ClusterAnalyzer::countFailedDeliveries($date, $hour);
 *
 * $description = ClusterAnalyzer::generateClusterDescription(
 *     $date,
 *     $hour,
 *     $failedCount,
 *     $successCount
 * );
 * // "Complete failure cluster on Jan 1, 2025 at 2:00-2:59 PM. 15 notification(s) affected."
 * ```
 *
 * @author DCPL Development Team
 *
 * @since 1.0.0
 * @see \Dcplibrary\Notices\Services\NotificationService
 */
class ClusterAnalyzer
{
    /**
     * Count successful email deliveries at a specific date and hour.
     *
     * Counts email notifications (delivery_option_id = 2) that were
     * successfully delivered during the specified hour window.
     *
     * Used to determine if a failure cluster is complete (no successes)
     * or partial (some succeeded despite failures).
     *
     * IMPORTANT: The database stores timestamps in UTC, but Laravel converts them
     * to the app timezone (America/Chicago) when reading. This method accepts
     * date/hour in the app timezone and converts to UTC for querying.
     *
     * @param string $date Date in Y-m-d format in app timezone (e.g., '2025-01-01')
     * @param int    $hour Hour in 24-hour format in app timezone (0-23)
     *
     * @return int Number of successfully delivered emails during that hour
     *
     * @example
     * ```php
     * // Count successful email deliveries on Jan 1, 2025 between 2:00-2:59 PM Chicago time
     * $count = ClusterAnalyzer::countSuccessfulDeliveries('2025-01-01', 14);
     * // Returns: 25 (25 emails successfully delivered)
     * ```
     *
     * @see self::getClusterType() To determine if failures were complete or partial
     */
    public static function countSuccessfulDeliveries(string $date, int $hour): int
    {
        // Convert app timezone date/hour to UTC for database query
        // Example: 2026-01-01 08:00 CST → 2026-01-01 14:00 UTC
        [$start, $end] = TimezoneHelper::getUtcRangeForHour($date, $hour);

        return Notification::where('delivery_option_id', 2)
            ->where('status', 'delivered')
            ->where('first_exported_at', '>=', $start)
            ->where('first_exported_at', '<', $end)
            ->count()
        ;
    }

    /**
     * Count failed email notifications at a specific date and hour.
     *
     * Counts email notifications (delivery_option_id = 2) that failed
     * during the specified hour window.
     *
     * IMPORTANT: The database stores timestamps in UTC, but Laravel converts them
     * to the app timezone (America/Chicago) when reading. This method accepts
     * date/hour in the app timezone and converts to UTC for querying.
     *
     * @param string $date Date in Y-m-d format in app timezone (e.g., '2025-01-01')
     * @param int    $hour Hour in 24-hour format in app timezone (0-23)
     *
     * @return int Number of failed emails during that hour
     *
     * @example
     * ```php
     * // Count failed email deliveries on Jan 1, 2025 between 2:00-2:59 PM Chicago time
     * $count = ClusterAnalyzer::countFailedDeliveries('2025-01-01', 14);
     * // Returns: 10 (10 emails failed)
     * ```
     */
    public static function countFailedDeliveries(string $date, int $hour): int
    {
        // Convert app timezone date/hour to UTC for database query
        // Example: 2026-01-01 08:00 CST → 2026-01-01 14:00 UTC
        [$start, $end] = TimezoneHelper::getUtcRangeForHour($date, $hour);

        return Notification::where('delivery_option_id', 2)
            ->where('status', 'failed')
            ->where('first_exported_at', '>=', $start)
            ->where('first_exported_at', '<', $end)
            ->count()
        ;
    }

    /**
     * Determine if a cluster is a partial failure (some succeeded) or complete failure (all failed).
     *
     * Classification logic:
     * - **Complete failure**: successCount = 0 (suggests systemic issue like service down)
     * - **Partial failure**: successCount > 0 (suggests patron-specific or random issues)
     *
     * @param int $successCount Number of successful deliveries during the same hour
     *
     * @return string "Partial failure" or "Complete failure"
     *
     * @example
     * ```php
     * ClusterAnalyzer::getClusterType(0);  // "Complete failure" - all failed
     * ClusterAnalyzer::getClusterType(5);  // "Partial failure" - some succeeded
     * ClusterAnalyzer::getClusterType(25); // "Partial failure" - many succeeded
     * ```
     */
    public static function getClusterType(int $successCount): string
    {
        return $successCount > 0 ? 'Partial failure' : 'Complete failure';
    }

    /**
     * Generate a human-readable cluster description.
     *
     * Creates a formatted description string suitable for displaying in
     * issue details, dashboard cards, and notification detail views.
     *
     * The description includes:
     * - Cluster type (Complete/Partial failure)
     * - Date in readable format (e.g., "Dec 25, 2025")
     * - Time range in 12-hour format (e.g., "2:00-2:59 PM")
     * - Number of failed notifications
     * - Number of successful notifications (if partial failure)
     *
     * @param string $date         Date in Y-m-d format (e.g., '2025-12-25')
     * @param int    $hour         Hour in 24-hour format (0-23)
     * @param int    $failedCount  Number of failed notifications in this cluster
     * @param int    $successCount Number of successful notifications during same time
     *
     * @return string Formatted description suitable for display
     *
     * @example
     * ```php
     * // Complete failure example
     * ClusterAnalyzer::generateClusterDescription('2025-12-25', 14, 10, 0);
     * // Returns: "Complete failure cluster on Dec 25, 2025 at 2:00-2:59 PM. 10 notification(s) affected."
     * ```
     * @example
     * ```php
     * // Partial failure example
     * ClusterAnalyzer::generateClusterDescription('2025-12-25', 14, 10, 5);
     * // Returns: "Partial failure cluster on Dec 25, 2025 at 2:00-2:59 PM. 10 notification(s) affected. 5 email(s) sent successfully at the same time."
     * ```
     *
     * @uses TimeFormatter::to12HourRange() For user-friendly time formatting
     */
    public static function generateClusterDescription(
        string $date,
        int $hour,
        int $failedCount,
        int $successCount
    ): string {
        $clusterType   = self::getClusterType($successCount);
        $timeRange     = TimeFormatter::to12HourRange($hour);
        $dateFormatted = \Carbon\Carbon::parse($date)->format('M j, Y');

        $details = sprintf(
            '%s cluster on %s at %s. %d notification(s) affected.',
            $clusterType,
            $dateFormatted,
            $timeRange,
            $failedCount
        );

        if ($successCount > 0) {
            $details .= sprintf(' %d email(s) sent successfully at the same time.', $successCount);
        }

        return $details;
    }

    /**
     * Determine if a cluster meets the minimum threshold.
     *
     * Single failures are often noise (patron-specific issues, bad email addresses, etc.).
     * Clusters of 2+ failures indicate potential systemic issues worth investigating.
     *
     * @param int $count        Number of notifications in potential cluster
     * @param int $minThreshold Minimum required to be considered a cluster (default: 2)
     *
     * @return bool True if count meets or exceeds threshold
     *
     * @example
     * ```php
     * ClusterAnalyzer::meetsClusterThreshold(1);  // false - single failure, not a cluster
     * ClusterAnalyzer::meetsClusterThreshold(2);  // true - meets minimum
     * ClusterAnalyzer::meetsClusterThreshold(10); // true - significant cluster
     * ```
     * @example
     * ```php
     * // Custom threshold
     * ClusterAnalyzer::meetsClusterThreshold(3, 5); // false - needs 5+
     * ClusterAnalyzer::meetsClusterThreshold(5, 5); // true - exactly meets threshold
     * ```
     */
    public static function meetsClusterThreshold(int $count, int $minThreshold = 2): bool
    {
        return $count >= $minThreshold;
    }
}
