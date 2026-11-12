<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrphanedNotificationQueryService
{
    /**
     * Get orphaned notification analysis for a given lookback period.
     *
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     * CRITICAL: This method retrieves notifications ALREADY marked as failed!
     * It does NOT calculate orphaned status dynamically. Status is determined
     * at import time by notices:process-orphaned.
     * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     *
     * Orphaned notifications are email notifications that were exported from Polaris
     * but never sent by LX Starter (Polaris → LX Starter communication failures).
     *
     * How notifications become "orphaned" (at import time):
     * 1. Notification exported from Polaris (first_exported_at set)
     * 2. Remains in 'pending' status for 24+ hours
     * 3. THE FIRST IMPORT AFTER 24 HOURS runs reconciliation
     * 4. If STILL pending after reconciliation → marked as 'failed' with polaris_export_failure
     *
     * What this method does (for display):
     * - Retrieves notifications with status = 'failed'
     * - AND active polaris_export_failure issue
     * - Groups into clusters (2+ failures at same date+hour)
     * - Provides pattern analysis to identify systematic issues
     *
     * @param int $daysBack Number of days to look back (default: 7, recommended: 30 for pattern detection)
     *
     * @return array Analysis data containing:
     *               - clusters (Collection): Hourly clusters with 2+ failures, each containing:
     *               - date (string): Date of cluster (Y-m-d)
     *               - hour (int): Hour of day (0-23)
     *               - time_range (string): 12-hour time range (e.g., "2:00-2:59 PM")
     *               - count (int): Number of failures in cluster
     *               - notification_types (array): Breakdown by notification type
     *               - sample_ids (array): Up to 5 notification IDs for investigation
     *               - day_of_week (int): Day of week (0=Sunday, 6=Saturday)
     *               - timestamp (string): ISO timestamp of first failure
     *               - successful_at_same_time (int): Count of successful emails at same hour
     *               - partial_failure (bool): True if some emails succeeded at same time
     *               - daily_summary (Collection): Complete daily timeline for chart visualization, containing:
     *               - date (string): Date (Y-m-d)
     *               - count (int): Total orphaned notifications for that day (includes zero-count days)
     *               - total_orphaned (int): Total count of orphaned notifications
     *               - pattern_analysis (array): Pattern detection results:
     *               - has_pattern (bool): True if significant pattern detected
     *               - description (string): Human-readable pattern description
     *               - primary_time (int): Most common hour of failures
     *               - primary_day (string): Most common day of week
     *               - affected_types (array): Notification types most affected
     *
     * @see OrphanedNotificationScope For query scope implementation
     */
    public function getOrphanedNotifications(int $daysBack = 7): array
    {
        $cacheKey = sprintf('notices:orphaned:%d', $daysBack);

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($daysBack): array {
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // CRITICAL: ONLY show notifications ALREADY MARKED AS FAILED
            //
            // DO NOT calculate orphaned status dynamically with now()!
            // The dashboard should ONLY display notifications that have been
            // marked as failed by the import process (notices:process-orphaned).
            //
            // Orphaned status is determined AT IMPORT TIME, not when viewing
            // the dashboard. This prevents false positives from time passage.
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

            // Get orphaned notifications (ALREADY marked as failed with polaris_export_failure)
            // These are notifications that were exported from Polaris but never sent by LX Starter
            $driver        = DB::getDriverName();
            $dayOfWeekExpr = match ($driver) {
                'sqlite' => 'STRFTIME("%w", notifications.first_exported_at)',
                // MySQL, MariaDB
                'mysql' => 'DAYOFWEEK(notifications.first_exported_at) - 1',
                // Fallback for other drivers: extract day of week via generic function if available
                default => 'EXTRACT(DOW FROM notifications.first_exported_at)',
            };
            $hourExpr = match ($driver) {
                'sqlite' => 'STRFTIME("%H", notifications.first_exported_at)',
                'mysql'  => 'HOUR(notifications.first_exported_at)',
                default  => 'EXTRACT(HOUR FROM notifications.first_exported_at)',
            };

            $orphaned = Notification::select([
                'notifications.id',
                'notifications.patron_barcode',
                'notifications.notification_type',
                'notifications.first_exported_at',
                'notifications.is_combined',
                'notifications.status',
                'notifications.failure_reason',
                'notifications.failed_at',
                DB::raw('DATE(notifications.first_exported_at) as export_date'),
                DB::raw('TIME(notifications.first_exported_at) as export_time'),
                DB::raw($dayOfWeekExpr . ' as day_of_week'),
                DB::raw($hourExpr . ' as hour'),
            ])
                ->where('notifications.delivery_option_id', 2) // Email only
                ->where('notifications.status', 'failed') // ONLY show those already marked as failed
                // Include both:
                // - classic "orphaned" failures flagged as polaris_export_failure issues
                // - blank/NULL delivery_string failures (Polaris/LX Starter errors) flagged by reconciler
                ->where(function ($q) {
                    $q->whereHas('issues', function ($subq) {
                        $subq->where('issue_type', 'polaris_export_failure');
                    })
                        ->orWhere('notifications.failure_reason', 'LIKE', '%LX Starter communication error%')
                        ->orWhere('notifications.failure_reason', 'LIKE', '%Polaris/LX Starter error: NotificationLog delivery_string is empty%')
                    ;
                })
                ->where('notifications.first_exported_at', '>=', now()->subDays($daysBack))
                ->orderBy('notifications.first_exported_at', 'desc')
                ->get()
            ;

            // Group into clusters by date and hour (minimum 2 to be a cluster)
            // Use first_exported_at Carbon instance which is automatically converted to app timezone
            $clusters = $orphaned->groupBy(function ($notification) {
                // first_exported_at is a Carbon instance in app timezone
                $timestamp = \Carbon\Carbon::parse($notification->first_exported_at);

                return $timestamp->format('Y-m-d H') . ':00';
            })->filter(function ($group) {
                return $group->count() >= 2; // Only clusters with 2+ failures
            })->map(function ($group, $key) {
                $parts = explode(' ', $key);
                $date  = $parts[0];
                $hour  = $parts[1];

                // Check if any emails were successfully delivered at the same time
                $hourInt         = (int) substr($hour, 0, 2);
                $successfulCount = \Dcplibrary\Notices\Support\ClusterAnalyzer::countSuccessfulDeliveries($date, $hourInt);

                // Convert to 12-hour format
                $hour12        = $hourInt % 12 ?: 12;
                $ampm          = $hourInt < 12 ? 'AM' : 'PM';
                $timeRange12Hr = sprintf('%d:00-%d:59 %s', $hour12, $hour12, $ampm);

                return [
                    'date'                    => $date,
                    'hour'                    => $hourInt,
                    'time_range'              => $timeRange12Hr,
                    'count'                   => $group->count(),
                    'notification_types'      => $group->groupBy('notification_type')->map->count()->toArray(),
                    'sample_ids'              => $group->pluck('id')->take(5)->toArray(),
                    'day_of_week'             => $group->first()->day_of_week,
                    'timestamp'               => $group->first()->first_exported_at,
                    'successful_at_same_time' => $successfulCount,
                    'partial_failure'         => $successfulCount > 0,
                ];
            })->sortByDesc('timestamp')->values();

            // Analyze patterns
            $patternAnalysis = $this->analyzeOrphanedPatterns($orphaned);

            // Create daily summary for chart (includes zero-count days for better visualization)
            $dailySummary = collect();
            $endDate      = now();
            $startDate    = now()->subDays($daysBack - 1)->startOfDay();

            // Build array of all dates in range
            $allDates    = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $allDates[$currentDate->format('Y-m-d')] = 0;
                $currentDate->addDay();
            }

            // Fill in actual counts
            $orphaned->groupBy('export_date')->each(function ($group, $date) use (&$allDates) {
                $allDates[$date] = $group->count();
            });

            // Convert to collection for chart
            foreach ($allDates as $date => $count) {
                $dailySummary->push([
                    'date'  => $date,
                    'count' => $count,
                ]);
            }

            return [
                'clusters'         => $clusters,
                'daily_summary'    => $dailySummary->sortBy('date')->values(),
                'total_orphaned'   => $orphaned->count(),
                'pattern_analysis' => $patternAnalysis,
            ];
        });
    }

    /**
     * Analyze patterns in orphaned notifications.
     *
     * Identifies common factors like time of day, day of week, notification types.
     *
     * @param Collection $orphaned Collection of orphaned notifications
     *
     * @return array Pattern analysis with description and affected filters
     */
    protected function analyzeOrphanedPatterns(Collection $orphaned): array
    {
        if ($orphaned->isEmpty()) {
            return [
                'has_pattern'    => false,
                'description'    => 'No orphaned notifications detected.',
                'primary_time'   => null,
                'primary_day'    => null,
                'affected_types' => [],
            ];
        }

        // Find most common hour
        $hourCounts            = $orphaned->groupBy('hour')->map->count()->sortDesc();
        $primaryHour           = $hourCounts->keys()->first();
        $primaryHourCount      = $hourCounts->first();
        $primaryHourPercentage = round(($primaryHourCount / $orphaned->count()) * 100);

        // Find most common day of week
        $dayNames             = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayCounts            = $orphaned->groupBy('day_of_week')->map->count()->sortDesc();
        $primaryDayNum        = $dayCounts->keys()->first();
        $primaryDayCount      = $dayCounts->first();
        $primaryDayPercentage = round(($primaryDayCount / $orphaned->count()) * 100);
        $primaryDay           = $dayNames[$primaryDayNum] ?? 'Unknown';

        // Get affected notification types
        $typeCounts = $orphaned->groupBy('notification_type')->map->count()->sortDesc();

        // Build pattern description
        $description    = '';
        $hasTimePattern = $primaryHourPercentage >= 50;
        $hasDayPattern  = $primaryDayPercentage  >= 40;

        if ($hasTimePattern && $hasDayPattern) {
            // Convert to 12-hour format
            $hour12      = $primaryHour % 12 ?: 12;
            $ampm        = $primaryHour < 12 ? 'AM' : 'PM';
            $description = sprintf(
                'Strong pattern detected: %d%% of failures occur on %ss around %d:00-%d:59 %s. ',
                $primaryDayPercentage,
                $primaryDay,
                $hour12,
                $hour12,
                $ampm
            );
        } elseif ($hasTimePattern) {
            // Convert to 12-hour format
            $hour12      = $primaryHour % 12 ?: 12;
            $ampm        = $primaryHour < 12 ? 'AM' : 'PM';
            $description = sprintf(
                'Time-based pattern detected: %d%% of failures occur between %d:00-%d:59 %s. ',
                $primaryHourPercentage,
                $hour12,
                $hour12,
                $ampm
            );
        } elseif ($hasDayPattern) {
            $description = sprintf(
                'Day-based pattern detected: %d%% of failures occur on %ss. ',
                $primaryDayPercentage,
                $primaryDay
            );
        } else {
            $description = 'No strong temporal pattern detected. Failures appear scattered across different times and days. ';
        }

        // Add notification type info
        $topType           = $typeCounts->keys()->first();
        $topTypeCount      = $typeCounts->first();
        $topTypePercentage = round(($topTypeCount / $orphaned->count()) * 100);

        if ($topTypePercentage >= 40) {
            $description .= sprintf(
                '%s notifications are most affected (%d%%).',
                $topType,
                $topTypePercentage
            );
        }

        // Convert 24-hour time to 12-hour format
        $primaryTime12Hr = null;
        if ($hasTimePattern) {
            $hour12          = $primaryHour % 12 ?: 12;
            $ampm            = $primaryHour < 12 ? 'AM' : 'PM';
            $primaryTime12Hr = sprintf('%d:00-%d:59 %s', $hour12, $hour12, $ampm);
        }

        return [
            'has_pattern'       => $hasTimePattern || $hasDayPattern,
            'description'       => $description,
            'primary_time'      => $primaryTime12Hr,
            'primary_day'       => $hasDayPattern ? $primaryDay : null,
            'affected_types'    => $typeCounts->toArray(),
            'time_distribution' => $hourCounts->take(5)->toArray(),
            'day_distribution'  => $dayCounts->mapWithKeys(function ($count, $dayNum) use ($dayNames) {
                return [$dayNames[$dayNum] ?? 'Unknown' => $count];
            })->toArray(),
        ];
    }
}
