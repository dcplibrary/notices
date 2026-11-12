<?php

namespace Dcplibrary\Notices\Services;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\Notification;
use Dcplibrary\Notices\Models\PatronDeliveryIssue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationMetricsQueryService
{
    /**
     * Get daily notification statistics broken down by type and delivery method.
     *
     * Wrapper for getDateRangeStatistics() for single-day queries.
     * Maintains backward compatibility with existing code.
     *
     * @param string $date Date in Y-m-d format (e.g., '2024-12-24')
     *
     * @return array Statistics array with keys: totals, by_type, by_method, issues
     *
     * @see self::getDateRangeStatistics()
     */
    public function getDailyStatistics(string $date): array
    {
        return $this->getDateRangeStatistics($date, $date);
    }

    /**
     * Get date range notification statistics broken down by type and delivery method.
     *
     * Returns comprehensive statistics for notifications exported within the date range:
     *
     * Structure:
     * [
     *   'totals' => object {
     *     total: int,           // Total notifications
     *     delivered: int,       // Successfully delivered count
     *     failed: int,          // Failed delivery count
     *     pending: int,         // Pending/unknown status count
     *     success_rate: float   // Percentage delivered (0-100)
     *   },
     *   'by_type' => Collection [
     *     'Hold Notice' => object { total, delivered, failed, pending, success_rate },
     *     'Overdue Notice' => object { ... },
     *     ...
     *   ],
     *   'by_method' => Collection [
     *     'E-mail' => object { total, delivered, failed, pending },
     *     'Voice Notification' => object { ... },
     *     ...
     *   ],
     *   'issues' => Collection [
     *     'failed_delivery_string' => int,  // Count of this issue type
     *     'manual_review' => int,
     *     ...
     *   ]
     * ]
     *
     * @param string $dateFrom Start date in Y-m-d format
     * @param string $dateTo   End date in Y-m-d format (inclusive)
     *
     * @return array Statistics with totals, by_type, by_method, and issues
     */
    public function getDateRangeStatistics(string $dateFrom, string $dateTo): array
    {
        $cacheKey = sprintf('notices:stats:date_range:%s:%s', $dateFrom, $dateTo);

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($dateFrom, $dateTo): array {
            $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
            $rangeEnd   = CarbonImmutable::parse($dateTo)->addDay()->startOfDay();

            // Overall totals
            $totals = Notification::query()
                ->where('first_exported_at', '>=', $rangeStart)
                ->where('first_exported_at', '<', $rangeEnd)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                    ROUND(100.0 * SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) / COUNT(*), 1) as success_rate
                ')
                ->first()
            ;

            // Breakdown by notification type
            $byType = Notification::query()
                ->where('first_exported_at', '>=', $rangeStart)
                ->where('first_exported_at', '<', $rangeEnd)
                ->selectRaw('
                    notification_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                    ROUND(100.0 * SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) / COUNT(*), 1) as success_rate
                ')
                ->groupBy('notification_type')
                ->orderBy('notification_type')
                ->get()
                ->keyBy('notification_type')
            ;

            // Breakdown by delivery method
            $byMethod = DB::table('notifications as n')
                ->leftJoin('delivery_options as do', 'n.delivery_option_id', '=', 'do.id')
                ->where('n.first_exported_at', '>=', $rangeStart)
                ->where('n.first_exported_at', '<', $rangeEnd)
                ->selectRaw('
                    COALESCE(do.delivery_option, "Unknown") as delivery_method,
                    COUNT(*) as total,
                    SUM(CASE WHEN n.status = "delivered" THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN n.status = "failed" THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN n.status = "pending" THEN 1 ELSE 0 END) as pending
                ')
                ->groupBy('do.delivery_option')
                ->get()
                ->keyBy('delivery_method')
            ;

            // Active issues summary
            $issues = PatronDeliveryIssue::whereNull('resolved_at')
                ->selectRaw('issue_type, COUNT(*) as count')
                ->groupBy('issue_type')
                ->get()
                ->pluck('count', 'issue_type')
            ;

            return [
                'totals'    => $totals,
                'by_type'   => $byType,
                'by_method' => $byMethod,
                'issues'    => $issues,
            ];
        });
    }

    /**
     * Get failed notification counts grouped by delivery method (delivery_option_id).
     *
     * If $dateFrom/$dateTo are omitted, this returns all-time counts.
     *
     * Labels MUST use override-aware display labels (lookup_table_overrides).
     *
     * @return Collection<int, object{delivery_option_id:null|int,label:string,failed:int}>
     */
    public function getFailedNotificationCountsByDeliveryMethod(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        // Include a version so shape/label changes don't get stuck behind cache.
        $cacheKey = sprintf('notices:stats:v2:failed_by_method:%s:%s', $dateFrom ?: 'all', $dateTo ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($dateFrom, $dateTo): Collection {
            $query = DB::table('notifications as n')
                ->where('n.status', 'failed')
                ->selectRaw('n.delivery_option_id as delivery_option_id')
                ->selectRaw('COUNT(*) as failed')
                ->groupBy('n.delivery_option_id')
                ->orderByDesc('failed')
            ;

            if (!empty($dateFrom) && !empty($dateTo)) {
                $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
                $rangeEnd   = CarbonImmutable::parse($dateTo)->addDay()->startOfDay();
                $query->where('n.first_exported_at', '>=', $rangeStart)
                    ->where('n.first_exported_at', '<', $rangeEnd)
                ;
            }

            /** @var Collection<int, object{delivery_option_id:null|int,failed:int}> $rows */
            $rows = $query->get();

            $labels = app(LookupLabelService::class);

            return $rows
                ->map(function ($row) use ($labels) {
                    $id = isset($row->delivery_option_id) ? (int) $row->delivery_option_id : null;

                    return (object) [
                        'delivery_option_id' => $id,
                        'label'              => $id ? $labels->getDeliveryLabel($id) : 'Unknown',
                        'failed'             => (int) ($row->failed ?? 0),
                    ];
                })
                ->sortByDesc('failed')
                ->values()
            ;
        });
    }

    /**
     * Get failed notification counts grouped by notification type.
     *
     * If $dateFrom/$dateTo are omitted, this returns all-time counts.
     *
     * Labels MUST use override-aware display labels (lookup_table_overrides).
     *
     * @return Collection<int, object{notification_type_id:null|int,notification_type:string,failed:int}>
     */
    public function getFailedNotificationCountsByNotificationType(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        // Include a version so shape/label changes don't get stuck behind cache.
        $cacheKey = sprintf('notices:stats:v2:failed_by_type:%s:%s', $dateFrom ?: 'all', $dateTo ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($dateFrom, $dateTo): Collection {
            $query = DB::table('notifications as n')
                ->where('n.status', 'failed')
                ->selectRaw('n.notification_type_id as notification_type_id')
                ->selectRaw('n.notification_type as notification_type_raw')
                ->selectRaw('COUNT(*) as failed')
                ->groupBy('n.notification_type_id', 'n.notification_type')
                ->orderByDesc('failed')
            ;

            if (!empty($dateFrom) && !empty($dateTo)) {
                $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
                $rangeEnd   = CarbonImmutable::parse($dateTo)->addDay()->startOfDay();
                $query->where('n.first_exported_at', '>=', $rangeStart)
                    ->where('n.first_exported_at', '<', $rangeEnd)
                ;
            }

            /** @var Collection<int, object{notification_type_id:null|int,notification_type_raw:null|string,failed:int}> $rows */
            $rows = $query->get();

            $labels = app(LookupLabelService::class);

            // Build a "description -> id" map so we can still apply overrides even if older data
            // didn't populate notification_type_id on notifications.
            $typesByNormalizedDescription = \Dcplibrary\Notices\Models\NotificationType::query()
                ->get()
                ->keyBy(function ($type) {
                    return strtolower(trim((string) $type->description));
                })
            ;

            $normalized = static function (?string $value): string {
                return strtolower(trim((string) $value));
            };

            $collapsed = $rows->map(function ($row) use ($labels, $typesByNormalizedDescription, $normalized) {
                $id  = isset($row->notification_type_id) ? (int) $row->notification_type_id : null;
                $raw = isset($row->notification_type_raw) ? (string) $row->notification_type_raw : '';

                if (!$id && '' !== $raw) {
                    $match = $typesByNormalizedDescription->get($normalized($raw));
                    $id    = $match?->id ? (int) $match->id : null;
                }

                return (object) [
                    'notification_type_id' => $id,
                    'notification_type'    => $id ? $labels->getNotificationTypeLabel($id) : ('' !== $raw ? $raw : 'Unknown'),
                    'failed'               => (int) ($row->failed ?? 0),
                ];
            });

            // If we resolved IDs for some rows and not others, collapse by resolved ID/label.
            return $collapsed
                ->groupBy(function ($row) {
                    return $row->notification_type_id ? ('id:' . $row->notification_type_id) : ('label:' . $row->notification_type);
                })
                ->map(function ($group) {
                    $first = $group->first();

                    return (object) [
                        'notification_type_id' => $first->notification_type_id,
                        'notification_type'    => $first->notification_type,
                        'failed'               => (int) $group->sum('failed'),
                    ];
                })
                ->sortByDesc('failed')
                ->values()
            ;
        });
    }

    /**
     * Count Email failures attributed to the Polaris → LX Starter pipeline.
     *
     * This includes:
     * - Notifications with a polaris_export_failure issue (active or resolved)
     * - Notifications failed with an LX Starter communication failure reason
     * - Notifications failed due to blank NotificationLog delivery_string (system/data failure)
     *
     * If $dateFrom/$dateTo are omitted, this returns all-time count.
     */
    public function countLxStarterFailures(?string $dateFrom = null, ?string $dateTo = null): int
    {
        $cacheKey = sprintf('notices:stats:lx_starter_failures:%s:%s', $dateFrom ?: 'all', $dateTo ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($dateFrom, $dateTo): int {
            $query = Notification::query()
                ->where('delivery_option_id', 2)
                ->where('status', 'failed')
                ->where(function ($q) {
                    $q->whereHas('issues', function ($subq) {
                        $subq->where('issue_type', 'polaris_export_failure');
                    })
                        ->orWhere('failure_reason', 'LIKE', '%LX Starter communication error%')
                        ->orWhere('failure_reason', 'LIKE', '%Polaris/LX Starter error: NotificationLog delivery_string is empty%')
                    ;
                })
            ;

            if (!empty($dateFrom) && !empty($dateTo)) {
                $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
                $rangeEnd   = CarbonImmutable::parse($dateTo)->addDay()->startOfDay();
                $query->where('first_exported_at', '>=', $rangeStart)
                    ->where('first_exported_at', '<', $rangeEnd)
                ;
            }

            return (int) $query->count();
        });
    }

    /**
     * Get statistics for active (unresolved) delivery issues.
     *
     * Provides comprehensive metrics for the issues dashboard. All counts and
     * statistics focus on ACTIVE (unresolved) issues only, except for resolution
     * metrics which naturally require resolved issues.
     *
     * Returned statistics:
     * - total_active: Count of unresolved issues
     * - total_resolved: Count of all resolved issues (all time)
     * - detected_today: Count of unresolved issues detected today
     * - resolved_today: Count of issues resolved today
     * - avg_resolution_hours: Average time to resolve issues (last 30 days), rounded to 1 decimal
     * - by_type: Breakdown of active issues by type (failed_delivery_string, manual_review, system_error)
     *
     * @return array Associative array of statistics for dashboard display
     */
    public function getIssueStatistics(): array
    {
        return Cache::remember('notices:stats:issues', now()->addMinutes(2), function (): array {
            $now        = now();
            $todayStart = $now->copy()->startOfDay();
            $todayEnd   = $todayStart->copy()->addDay();

            // Total active (unresolved) issues
            $totalActive = PatronDeliveryIssue::whereNull('resolved_at')->count();

            // Breakdown by issue type (active only)
            $byType = PatronDeliveryIssue::whereNull('resolved_at')
                ->select('issue_type', DB::raw('COUNT(*) as count'))
                ->groupBy('issue_type')
                ->get()
                ->pluck('count', 'issue_type')
                ->toArray()
            ;

            // Issues detected today (active only)
            $detectedToday = PatronDeliveryIssue::query()
                ->where('detected_at', '>=', $todayStart)
                ->where('detected_at', '<', $todayEnd)
                ->whereNull('resolved_at')
                ->count()
            ;

            // Issues resolved today
            $resolvedToday = PatronDeliveryIssue::query()
                ->where('resolved_at', '>=', $todayStart)
                ->where('resolved_at', '<', $todayEnd)
                ->count()
            ;

            // Average time to resolution (in hours) for issues resolved in last 30 days
            // Note: Different SQL for SQLite vs MySQL
            $driver = DB::connection()->getDriverName();

            if ('sqlite' === $driver) {
                // SQLite: Use julianday to calculate difference in days, convert to hours
                $avgResolutionTime = PatronDeliveryIssue::whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', $now->copy()->subDays(30))
                    ->selectRaw('AVG((julianday(resolved_at) - julianday(detected_at)) * 24) as avg_hours')
                    ->value('avg_hours')
                ;
            } else {
                // MySQL/MariaDB: Use TIMESTAMPDIFF
                $avgResolutionTime = PatronDeliveryIssue::whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', $now->copy()->subDays(30))
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, detected_at, resolved_at)) as avg_hours')
                    ->value('avg_hours')
                ;
            }

            // Total resolved (all time)
            $totalResolved = PatronDeliveryIssue::whereNotNull('resolved_at')->count();

            return [
                'total_active'         => $totalActive,
                'total_resolved'       => $totalResolved,
                'detected_today'       => $detectedToday,
                'resolved_today'       => $resolvedToday,
                'avg_resolution_hours' => $avgResolutionTime ? round($avgResolutionTime, 1) : null,
                'by_type'              => [
                    'failed_delivery_string' => $byType['failed_delivery_string'] ?? 0,
                    'manual_review'          => $byType['manual_review']          ?? 0,
                    'system_error'           => $byType['system_error']           ?? 0,
                ],
            ];
        });
    }
}
