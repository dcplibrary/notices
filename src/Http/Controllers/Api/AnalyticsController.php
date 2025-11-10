<?php

namespace Dcplibrary\Notices\Http\Controllers\Api;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get dashboard overview statistics.
     */
    public function overview(Request $request): array
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        // Get totals from daily summaries
        $totals = DailyNotificationSummary::getAggregatedTotals($startDate, $endDate);

        // Get recent activity (last 7 days)
        $recentActivity = NotificationLog::recent(7)->count();

        // Get breakdown by type
        $byType = DailyNotificationSummary::getBreakdownByType($startDate, $endDate);

        // Get breakdown by delivery
        $byDelivery = DailyNotificationSummary::getBreakdownByDelivery($startDate, $endDate);

        // Get trend data (daily totals for chart)
        $trend = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->select(
                'summary_date',
                DB::raw('SUM(total_sent) as total_sent'),
                DB::raw('SUM(total_success) as total_success'),
                DB::raw('SUM(total_failed) as total_failed')
            )
            ->groupBy('summary_date')
            ->orderBy('summary_date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->summary_date->format('Y-m-d'),
                'sent' => $item->total_sent,
                'success' => $item->total_success,
                'failed' => $item->total_failed,
            ]);

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $days,
            ],
            'totals' => $totals,
            'recent_activity' => $recentActivity,
            'by_type' => $byType,
            'by_delivery' => $byDelivery,
            'trend' => $trend,
        ];
    }

    /**
     * Get time series data for charts.
     */
    public function timeSeries(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));
        $groupBy = $request->input('group_by', 'day'); // day, week, month

        $query = DailyNotificationSummary::dateRange($startDate, $endDate);

        // Group by period
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $results = $query
            ->select(
                DB::raw("DATE_FORMAT(summary_date, '{$dateFormat}') as period"),
                DB::raw('SUM(total_sent) as total_sent'),
                DB::raw('SUM(total_success) as total_success'),
                DB::raw('SUM(total_failed) as total_failed'),
                DB::raw('AVG(success_rate) as avg_success_rate')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'group_by' => $groupBy,
            'data' => $results,
        ];
    }

    /**
     * Get top patrons by notification count.
     */
    public function topPatrons(Request $request): array
    {
        $days = $request->input('days', 30);
        $limit = min($request->input('limit', 10), 100);

        $topPatrons = NotificationLog::recent($days)
            ->select('patron_id', 'patron_barcode', DB::raw('COUNT(*) as notification_count'))
            ->whereNotNull('patron_id')
            ->groupBy('patron_id', 'patron_barcode')
            ->orderByDesc('notification_count')
            ->limit($limit)
            ->get();

        return $topPatrons->toArray();
    }

    /**
     * Get success rate trends over time.
     */
    public function successRateTrend(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        $trend = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->select(
                'summary_date',
                DB::raw('SUM(total_sent) as total_sent'),
                DB::raw('SUM(total_success) as total_success'),
                DB::raw('SUM(total_failed) as total_failed'),
                DB::raw('
                    CASE
                        WHEN SUM(total_sent) > 0
                        THEN ROUND((SUM(total_success) / SUM(total_sent)) * 100, 2)
                        ELSE 0
                    END as success_rate
                ')
            )
            ->groupBy('summary_date')
            ->orderBy('summary_date')
            ->get();

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'trend' => $trend,
        ];
    }
}
