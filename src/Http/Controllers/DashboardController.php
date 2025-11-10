<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Models\ShoutbombRegistration;
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
        $days = $request->input('days', config('notices.dashboard.default_date_range', 30));
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
        $notificationTypes = config('notices.notification_types', []);
        $deliveryOptions = config('notices.delivery_options', []);
        $notificationStatuses = config('notices.notification_statuses', []);

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
                    THEN ROUND((SUM(total_success) * 100.0 / SUM(total_sent)), 2)
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

        // Get submission statistics (official SQL-generated files)
        $submissionStats = \Dcplibrary\Notifications\Models\ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_submissions,
                COUNT(DISTINCT patron_barcode) as unique_patrons,
                SUM(CASE WHEN notification_type = "holds" THEN 1 ELSE 0 END) as holds_count,
                SUM(CASE WHEN notification_type = "overdue" THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN notification_type = "renew" THEN 1 ELSE 0 END) as renew_count,
                SUM(CASE WHEN delivery_type = "voice" THEN 1 ELSE 0 END) as voice_count,
                SUM(CASE WHEN delivery_type = "text" THEN 1 ELSE 0 END) as text_count
            ')
            ->first();

        // Get phone notices statistics (verification/corroboration)
        $phoneNoticeStats = \Dcplibrary\Notifications\Models\ShoutbombPhoneNotice::whereBetween('notice_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_notices,
                COUNT(DISTINCT patron_barcode) as unique_patrons,
                SUM(CASE WHEN delivery_type = "voice" THEN 1 ELSE 0 END) as voice_count,
                SUM(CASE WHEN delivery_type = "text" THEN 1 ELSE 0 END) as text_count
            ')
            ->first();

        // Daily submission trend
        $submissionTrend = \Dcplibrary\Notifications\Models\ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
            ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count, notification_type')
            ->groupBy('date', 'notification_type')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Daily phone notice trend
        $phoneNoticeTrend = \Dcplibrary\Notifications\Models\ShoutbombPhoneNotice::whereBetween('notice_date', [$startDate, $endDate])
            ->selectRaw('DATE(notice_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Get recent submissions for display
        $recentSubmissions = \Dcplibrary\Notifications\Models\ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
            ->orderBy('submitted_at', 'desc')
            ->limit(10)
            ->get();

        return view('notifications::dashboard.shoutbomb', compact(
            'days',
            'startDate',
            'endDate',
            'submissionStats',
            'phoneNoticeStats',
            'submissionTrend',
            'phoneNoticeTrend',
            'recentSubmissions'
        ));
    }
}
