<?php

namespace Dcplibrary\Notifications\Http\Controllers;

use Dcplibrary\Notifications\Models\NotificationLog;
use Dcplibrary\Notifications\Models\DailyNotificationSummary;
use Dcplibrary\Notifications\Models\ShoutbombRegistration;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the dashboard overview.
     */
    public function index(Request $request): View
    {
        $days = $request->input('days', config('notifications.dashboard.default_date_range', 30));
        $startDate = now()->subDays($days);
        $endDate = now();

        // Get aggregated totals
        $totals = DailyNotificationSummary::getAggregatedTotals($startDate, $endDate);

        // Get breakdown by type
        $byType = DailyNotificationSummary::getBreakdownByType($startDate, $endDate);

        // Get breakdown by delivery method
        $byDelivery = DailyNotificationSummary::getBreakdownByDelivery($startDate, $endDate);

        // Get daily trend data for chart
        $trendData = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->selectRaw('
                summary_date,
                SUM(total_sent) as total_sent,
                SUM(total_success) as total_success,
                SUM(total_failed) as total_failed
            ')
            ->groupBy('summary_date')
            ->orderBy('summary_date')
            ->get();

        // Get latest Shoutbomb registration stats
        $latestRegistration = ShoutbombRegistration::orderBy('snapshot_date', 'desc')->first();

        return view('notifications::dashboard.index', compact(
            'days',
            'startDate',
            'endDate',
            'totals',
            'byType',
            'byDelivery',
            'trendData',
            'latestRegistration'
        ));
    }

    /**
     * Display notifications list.
     */
    public function notifications(Request $request): View
    {
        $query = NotificationLog::query();

        // Apply filters
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );
        } else {
            $query->recent(30);
        }

        if ($request->has('type_id')) {
            $query->ofType((int) $request->type_id);
        }

        if ($request->has('delivery_id')) {
            $query->byDeliveryMethod((int) $request->delivery_id);
        }

        if ($request->has('status_id')) {
            $query->byStatus((int) $request->status_id);
        }

        // Sort
        $query->orderBy('notification_date', 'desc');

        $notifications = $query->paginate(50);

        // Get filter options
        $notificationTypes = config('notifications.notification_types', []);
        $deliveryOptions = config('notifications.delivery_options', []);
        $notificationStatuses = config('notifications.notification_statuses', []);

        return view('notifications::dashboard.notifications', compact(
            'notifications',
            'notificationTypes',
            'deliveryOptions',
            'notificationStatuses'
        ));
    }

    /**
     * Display analytics page.
     */
    public function analytics(Request $request): View
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        // Success rate trend
        $successRateTrend = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->selectRaw('
                summary_date,
                SUM(total_sent) as total_sent,
                SUM(total_success) as total_success,
                SUM(total_failed) as total_failed,
                CASE
                    WHEN SUM(total_sent) > 0
                    THEN ROUND((SUM(total_success) / SUM(total_sent)) * 100, 2)
                    ELSE 0
                END as success_rate
            ')
            ->groupBy('summary_date')
            ->orderBy('summary_date')
            ->get();

        // Type distribution
        $typeDistribution = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->selectRaw('
                notification_type_id,
                SUM(total_sent) as total_sent
            ')
            ->groupBy('notification_type_id')
            ->get();

        // Delivery method distribution
        $deliveryDistribution = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->selectRaw('
                delivery_option_id,
                SUM(total_sent) as total_sent
            ')
            ->groupBy('delivery_option_id')
            ->get();

        return view('notifications::dashboard.analytics', compact(
            'days',
            'startDate',
            'endDate',
            'successRateTrend',
            'typeDistribution',
            'deliveryDistribution'
        ));
    }

    /**
     * Display Shoutbomb statistics.
     */
    public function shoutbomb(Request $request): View
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        // Get registration history
        $registrationHistory = ShoutbombRegistration::whereBetween('snapshot_date', [$startDate, $endDate])
            ->orderBy('snapshot_date')
            ->get();

        // Get latest registration
        $latestRegistration = ShoutbombRegistration::orderBy('snapshot_date', 'desc')->first();

        return view('notifications::dashboard.shoutbomb', compact(
            'days',
            'startDate',
            'endDate',
            'registrationHistory',
            'latestRegistration'
        ));
    }
}
