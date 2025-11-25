<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Models\DeliveryMethod;
use Dcplibrary\Notices\Models\NotificationLog;
use Dcplibrary\Notices\Models\NotificationStatus;
use Dcplibrary\Notices\Models\NotificationType;
use Dcplibrary\Notices\Models\ShoutbombRegistration;
use Dcplibrary\Notices\Services\NoticeExportService;
use Dcplibrary\Notices\Services\NoticeVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard overview.
     */
    public function index(Request $request): View
    {
        $days = $request->input('days', config('notices.dashboard.default_date_range', 30));

        // Use data-relative dates: find the latest notification date and work backwards
        // This ensures the dashboard shows data even if it's older than $days from today
        $latestDate = NotificationLog::max('notification_date');
        if ($latestDate) {
            $endDate = $latestDate instanceof Carbon ? $latestDate : Carbon::parse($latestDate);
        } else {
            $endDate = now();
        }
        $startDate = $endDate->copy()->subDays($days);

        // Get aggregated totals (from analytics table)
        $totals = DailyNotificationSummary::getAggregatedTotals($startDate, $endDate);

        // Get breakdown by type (from analytics table)
        $byType = DailyNotificationSummary::getBreakdownByType($startDate, $endDate);

        // Get breakdown by delivery method (from analytics table)
        $byDelivery = DailyNotificationSummary::getBreakdownByDelivery($startDate, $endDate);

        // Fallback: if analytics table is empty for the range, compute from notification_logs directly
        $needsFallback = empty($totals) || ((int)($totals['total_sent'] ?? 0) === 0 && (count($byType) === 0) && (count($byDelivery) === 0));
        if ($needsFallback) {
            $totalsObj = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
                ->selectRaw('
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed,
                    SUM(holds_count) as total_holds,
                    SUM(overdues_count + overdues_2nd_count + overdues_3rd_count) as total_overdues
                ')
                ->first();

            $totals = [
                'total_sent' => (int) ($totalsObj->total_sent ?? 0),
                'total_success' => (int) ($totalsObj->total_success ?? 0),
                'total_failed' => (int) ($totalsObj->total_failed ?? 0),
                'total_holds' => (int) ($totalsObj->total_holds ?? 0),
                'total_overdues' => (int) ($totalsObj->total_overdues ?? 0),
                'avg_success_rate' => ($totalsObj->total_sent ?? 0) > 0
                    ? round((($totalsObj->total_success ?? 0) * 100.0 / ($totalsObj->total_sent ?? 1)), 2)
                    : 0,
            ];

            $byType = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
                ->selectRaw('notification_type_id, COUNT(*) as total_sent, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed')
                ->groupBy('notification_type_id')
                ->get()
                ->toArray();

            $byDelivery = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
                ->selectRaw('delivery_option_id, COUNT(*) as total_sent, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed')
                ->groupBy('delivery_option_id')
                ->get()
                ->toArray();
        }

        // Get daily trend data for chart (prefer analytics table)
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

        // Fallback to logs if analytics table returned no rows
        if ($trendData->isEmpty()) {
            $trendData = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
                ->selectRaw('DATE(notification_date) as summary_date, COUNT(*) as total_sent, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed')
                ->groupBy('summary_date')
                ->orderBy('summary_date')
                ->get();
        }
        // Ensure summary_date is a Carbon instance for the view
        $trendData = $trendData->map(function ($row) {
            if (is_string($row->summary_date)) {
                $row->summary_date = Carbon::parse($row->summary_date);
            }
            return $row;
        });

        // Success rate trend (from analytics)
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

        if ($successRateTrend->isEmpty()) {
            $successRateTrend = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
                ->selectRaw('
                    DATE(notification_date) as summary_date,
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed,
                    CASE WHEN COUNT(*) > 0 THEN ROUND((SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) ELSE 0 END as success_rate
                ')
                ->groupBy('summary_date')
                ->orderBy('summary_date')
                ->get();
        }
        // Ensure summary_date is a Carbon instance for the view
        $successRateTrend = $successRateTrend->map(function ($row) {
            if (is_string($row->summary_date)) {
                $row->summary_date = Carbon::parse($row->summary_date);
            }
            return $row;
        });

        // Type distribution with detailed breakdown (from analytics)
        $typeDistribution = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->selectRaw('
                notification_type_id,
                SUM(total_sent) as total_sent
            ')
            ->groupBy('notification_type_id')
            ->orderBy('total_sent', 'desc')
            ->get();

        // Delivery method distribution with detailed breakdown (from analytics)
        $deliveryDistribution = DailyNotificationSummary::dateRange($startDate, $endDate)
            ->selectRaw('
                delivery_option_id,
                SUM(total_sent) as total_sent
            ')
            ->groupBy('delivery_option_id')
            ->orderBy('total_sent', 'desc')
            ->get();

        // Get unique patrons by delivery method
        $patronsByDelivery = NotificationLog::whereBetween('notification_date', [$startDate, $endDate])
            ->selectRaw('
                delivery_option_id,
                COUNT(DISTINCT patron_barcode) as unique_patrons
            ')
            ->groupBy('delivery_option_id')
            ->orderBy('unique_patrons', 'desc')
            ->get();

        return view('notices::dashboard.index', compact(
            'days',
            'startDate',
            'endDate',
            'totals',
            'byType',
            'byDelivery',
            'trendData',
            'successRateTrend',
            'typeDistribution',
            'deliveryDistribution',
            'patronsByDelivery'
        ));
    }

    /**
     * Display notifications list.
     */
    public function notifications(Request $request): View
    {
        $query = NotificationLog::query();

        // Apply date range filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->dateRange($startDate, $endDate);
        } else {
            $query->recent(30);
        }

        // Apply type filter
        if ($request->filled('type_id') && $request->type_id > 0) {
            $query->ofType((int) $request->type_id);
        }

        // Apply delivery method filter
        if ($request->filled('delivery_id') && $request->delivery_id > 0) {
            $query->byDeliveryMethod((int) $request->delivery_id);
        }

        // Apply status filter
        if ($request->filled('status')) {
            $status = $request->input('status');
            if (in_array($status, ['completed', 'pending', 'failed'])) {
                $query->where('status', $status);
            }
        }
        
        // Legacy: Support old status_id filtering (supports single or multiple statuses)
        if ($request->filled('status_id')) {
            $statusId = $request->input('status_id');
            
            // Handle comma-separated string or array
            if (is_string($statusId) && str_contains($statusId, ',')) {
                $statusIds = array_map('intval', explode(',', $statusId));
                $query->whereIn('notification_status_id', $statusIds);
            } elseif (is_array($statusId)) {
                $query->whereIn('notification_status_id', array_map('intval', $statusId));
            } else {
                $query->byStatus((int) $statusId);
            }
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->input('search');

            // Find patron barcodes from Shoutbomb phone notices that match the search term
            $matchingBarcodes = \Dcplibrary\Notices\Models\PolarisPhoneNotice::where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->pluck('patron_barcode')
            ->unique()
            ->toArray();

            $query->where(function ($q) use ($search, $matchingBarcodes) {
                $q->where('patron_barcode', 'like', "%{$search}%")
                  ->orWhere('patron_id', $search)
                  ->orWhere('delivery_string', 'like', "%{$search}%");

                // Also search by patron name from imported data
                if (!empty($matchingBarcodes)) {
                    $q->orWhereIn('patron_barcode', $matchingBarcodes);
                }
            });
        }

        // Sort
        $sortField = $request->input('sort', 'notification_date');
        $sortDirection = $request->input('direction', 'desc');

        // Whitelist sortable fields
        $allowedSorts = [
            'notification_date',
            'patron_barcode',
            'notification_type_id',
            'delivery_option_id',
            'notification_status_id'
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('notification_date', 'desc');
        }

        $notifications = $query->paginate(50)->withQueryString();

        // Get filter options - only enabled items
        $notificationTypes = NotificationType::enabled()->ordered()->pluck('description', 'notification_type_id')->toArray();
        $deliveryOptions = DeliveryMethod::enabled()->ordered()->pluck('delivery_option', 'delivery_option_id')->toArray();
        
        // Filter detailed statuses by selected status category if applicable
        $notificationStatusesQuery = NotificationStatus::enabled()->ordered();
        if ($request->filled('status') && in_array($request->input('status'), ['completed', 'pending', 'failed'])) {
            $notificationStatusesQuery->where('category', $request->input('status'));
        }
        $notificationStatuses = $notificationStatusesQuery->pluck('description', 'notification_status_id')->toArray();

        return view('notices::dashboard.notifications', compact(
            'notifications',
            'notificationTypes',
            'deliveryOptions',
            'notificationStatuses'
        ));
    }

    /**
     * Display detailed view of a single notification.
     */
    public function notificationDetail(int $id): View
    {
        $notification = NotificationLog::findOrFail($id);

        // Eager load patron and items data from Polaris
        // This is done via accessors in the model

        return view('notices::dashboard.notification-detail', compact('notification'));
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

        // Top items by notification count (across all channels)
        $topItems = NotificationLog::whereBetween('notification_logs.notification_date', [$startDate, $endDate])
            ->join(
                \DB::raw('(
                    SELECT patron_barcode, notice_date, title, item_record_id, COUNT(*) as count
                    FROM ' . \DB::connection()->getTablePrefix() . 'polaris_phone_notices
                    WHERE notice_date BETWEEN ? AND ?
                    GROUP BY patron_barcode, notice_date, title, item_record_id
                ) as items'),
                'notification_logs.patron_barcode',
                '=',
                'items.patron_barcode'
            )
            ->addBinding([$startDate, $endDate], 'join')
            ->selectRaw('items.title, items.item_record_id, COUNT(notification_logs.id) as notification_count')
            ->groupBy('items.title', 'items.item_record_id')
            ->orderBy('notification_count', 'desc')
            ->limit(15)
            ->get();

        return view('notices::dashboard.analytics', compact(
            'days',
            'startDate',
            'endDate',
            'successRateTrend',
            'typeDistribution',
            'deliveryDistribution',
            'topItems'
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
        $submissionStats = \Dcplibrary\Notices\Models\ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
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
        $phoneNoticeStats = \Dcplibrary\Notices\Models\PolarisPhoneNotice::whereBetween('notice_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_notices,
                COUNT(DISTINCT patron_barcode) as unique_patrons,
                SUM(CASE WHEN delivery_type = "voice" THEN 1 ELSE 0 END) as voice_count,
                SUM(CASE WHEN delivery_type = "text" THEN 1 ELSE 0 END) as text_count
            ')
            ->first();

        // Daily submission trend
        $submissionTrend = \Dcplibrary\Notices\Models\ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
            ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count, notification_type')
            ->groupBy('date', 'notification_type')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Daily phone notice trend
        $phoneNoticeTrend = \Dcplibrary\Notices\Models\PolarisPhoneNotice::whereBetween('notice_date', [$startDate, $endDate])
            ->selectRaw('DATE(notice_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Get recent submissions for display
        $recentSubmissions = \Dcplibrary\Notices\Models\ShoutbombSubmission::whereBetween('submitted_at', [$startDate, $endDate])
            ->orderBy('submitted_at', 'desc')
            ->limit(10)
            ->get();

        // Get latest Shoutbomb registration stats
        $latestRegistration = ShoutbombRegistration::orderBy('snapshot_date', 'desc')->first();

        // Get registration history for trend chart
        $registrationHistory = ShoutbombRegistration::orderBy('snapshot_date', 'asc')
            ->limit(30)
            ->get();

        return view('notices::dashboard.shoutbomb', compact(
            'days',
            'startDate',
            'endDate',
            'submissionStats',
            'phoneNoticeStats',
            'submissionTrend',
            'phoneNoticeTrend',
            'recentSubmissions',
            'latestRegistration',
            'registrationHistory'
        ));
    }

    /**
     * Display verification search page.
     */
    public function verification(Request $request): View
    {
        $service = app(NoticeVerificationService::class);
        $results = collect();
        $summary = [
            'total' => 0,
            'verified' => 0,
            'failed' => 0,
            'pending' => 0,
        ];

        // Only run search if filter provided
        if ($request->hasAny(['patron_barcode', 'phone', 'email', 'item_barcode'])) {
            $query = NotificationLog::query();

            // Apply filters
            if ($request->filled('patron_barcode')) {
                $query->where('patron_barcode', $request->patron_barcode);
            }

            if ($request->filled('phone')) {
                $query->where('phone', 'like', '%' . $request->phone . '%');
            }

            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->email . '%');
            }

            if ($request->filled('item_barcode')) {
                $query->where('item_barcode', $request->item_barcode);
            }

            // Date range filter
            if ($request->has('date_from') && $request->has('date_to')) {
                $query->dateRange(
                    Carbon::parse($request->date_from),
                    Carbon::parse($request->date_to)
                );
            } else {
                $query->recent(30);
            }

            $notices = $query->orderBy('notification_date', 'desc')->limit(100)->get();

            // Verify each notice
            $results = $notices->map(function ($notice) use ($service) {
                return [
                    'notice' => $notice,
                    'verification' => $service->verify($notice),
                ];
            });

            // Calculate summary
            $summary['total'] = $results->count();
            $summary['verified'] = $results->filter(fn($r) => $r['verification']->overall_status === 'success')->count();
            $summary['failed'] = $results->filter(fn($r) => $r['verification']->overall_status === 'failed')->count();
            $summary['pending'] = $results->filter(fn($r) => $r['verification']->overall_status === 'pending')->count();
        }

        return view('notices::dashboard.verification', compact('results', 'summary'));
    }

    /**
     * Display timeline for a specific notice.
     */
    public function timeline(int $id): View
    {
        $notice = NotificationLog::findOrFail($id);
        $service = app(NoticeVerificationService::class);
        $verification = $service->verify($notice);

        return view('notices::dashboard.verification-timeline', compact('notice', 'verification'));
    }

    /**
     * Display patron verification history.
     */
    public function patronHistory(string $barcode): View
    {
        $service = app(NoticeVerificationService::class);

        // Get date range
        $days = request()->input('days', 90);
        $startDate = now()->subDays($days);
        $endDate = now();

        // Get all notices for this patron with verification
        $results = $service->verifyByPatron($barcode, $startDate, $endDate);

        // Calculate statistics
        $stats = [
            'total_notices' => count($results),
            'success_count' => collect($results)->filter(fn($r) => $r['verification']->overall_status === 'success')->count(),
            'failed_count' => collect($results)->filter(fn($r) => $r['verification']->overall_status === 'failed')->count(),
            'pending_count' => collect($results)->filter(fn($r) => $r['verification']->overall_status === 'pending')->count(),
        ];

        if ($stats['total_notices'] > 0) {
            $stats['success_rate'] = round(($stats['success_count'] / $stats['total_notices']) * 100, 1);
        } else {
            $stats['success_rate'] = 0;
        }

        // Group by type
        $byType = [];
        foreach ($results as $result) {
            $typeId = $result['notice']->notification_type_id;
            $typeName = config('notices.notification_types')[$typeId] ?? 'Unknown';

            if (!isset($byType[$typeName])) {
                $byType[$typeName] = 0;
            }
            $byType[$typeName]++;
        }

        return view('notices::dashboard.verification-patron', compact(
            'barcode',
            'results',
            'stats',
            'byType',
            'days',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Display troubleshooting dashboard.
     */
    public function troubleshooting(Request $request): View
    {
        try {
            $service = app(NoticeVerificationService::class);

            // Get date range
            $days = $request->input('days', 7);
            $startDate = now()->subDays($days);
            $endDate = now();

            // Get troubleshooting summary
            try {
                $summary = $service->getTroubleshootingSummary($startDate, $endDate);
            } catch (\Exception $e) {
                \Log::error('Error in getTroubleshootingSummary: ' . $e->getMessage());
                throw new \Exception('Failed to get troubleshooting summary: ' . $e->getMessage());
            }

            // Get failures grouped by reason
            try {
                $failuresByReason = $service->getFailuresByReason($startDate, $endDate);
            } catch (\Exception $e) {
                \Log::error('Error in getFailuresByReason: ' . $e->getMessage());
                $failuresByReason = [];
            }

            // Get failures grouped by type
            try {
                $failuresByType = $service->getFailuresByType($startDate, $endDate);
            } catch (\Exception $e) {
                \Log::error('Error in getFailuresByType: ' . $e->getMessage());
                $failuresByType = [];
            }

            // Get mismatches
            try {
                $mismatches = $service->getMismatches($startDate, $endDate);
            } catch (\Exception $e) {
                \Log::error('Error in getMismatches: ' . $e->getMessage());
                $mismatches = [
                    'submitted_not_verified' => [],
                    'pending_verification' => [],
                    'actually_failed' => [],
                ];
            }

            // Get recent failures (limit to 20 for display)
            try {
                $recentFailures = collect($service->getFailedNotices($startDate, $endDate))->take(20);
            } catch (\Exception $e) {
                \Log::error('Error in getFailedNotices: ' . $e->getMessage());
                $recentFailures = collect([]);
            }

            return view('notices::dashboard.troubleshooting', compact(
                'days',
                'startDate',
                'endDate',
                'summary',
                'failuresByReason',
                'failuresByType',
                'mismatches',
                'recentFailures'
            ));
        } catch (\Exception $e) {
            \Log::error('Troubleshooting page error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            abort(500, 'Error loading troubleshooting dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Export verification results to CSV.
     */
    public function exportVerification(Request $request): Response
    {
        $service = app(NoticeVerificationService::class);
        $exportService = app(NoticeExportService::class);

        $query = NotificationLog::query();

        // Apply same filters as verification page
        if ($request->has('patron_barcode')) {
            $query->where('patron_barcode', $request->patron_barcode);
        }

        if ($request->has('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->has('email')) {
            $query->where('email', $request->email);
        }

        if ($request->has('item_barcode')) {
            $query->where('item_barcode', $request->item_barcode);
        }

        // Date range
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->dateRange(
                Carbon::parse($request->date_from),
                Carbon::parse($request->date_to)
            );
        } else {
            $query->recent(30);
        }

        $notices = $query->orderBy('notification_date', 'desc')->limit(1000)->get();

        $csv = $exportService->exportVerificationToCSV($notices);

        $filename = 'notice-verification-' . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export patron history to CSV.
     */
    public function exportPatronHistory(Request $request, string $barcode): Response
    {
        $service = app(NoticeVerificationService::class);
        $exportService = app(NoticeExportService::class);

        $days = $request->input('days', 90);
        $startDate = now()->subDays($days);
        $endDate = now();

        $results = $service->verifyByPatron($barcode, $startDate, $endDate);

        $csv = $exportService->exportPatronHistoryToCSV($results);

        $filename = "patron-{$barcode}-verification-" . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export troubleshooting failures to CSV.
     */
    public function exportFailures(Request $request): Response
    {
        $service = app(NoticeVerificationService::class);
        $exportService = app(NoticeExportService::class);

        $days = $request->input('days', 7);
        $startDate = now()->subDays($days);
        $endDate = now();

        $failures = $service->getFailedNotices($startDate, $endDate);

        $csv = $exportService->exportFailuresToCSV($failures);

        $filename = 'notice-failures-' . now()->format('Y-m-d-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
