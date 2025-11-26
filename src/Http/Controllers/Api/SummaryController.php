<?php

namespace Dcplibrary\Notices\Http\Controllers\Api;

use Carbon\Carbon;
use Dcplibrary\Notices\Http\Resources\DailyNotificationSummaryResource;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class SummaryController extends Controller
{
    /**
     * Display a listing of daily summaries.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            $request->input('per_page', config('notices.api.per_page', 20)),
            config('notices.api.max_per_page', 100)
        );

        $query = DailyNotificationSummary::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );
        }

        // Filter by notification type
        if ($request->has('type_id')) {
            $query->ofType((int) $request->type_id);
        }

        // Filter by delivery method
        if ($request->has('delivery_id')) {
            $query->byDeliveryMethod((int) $request->delivery_id);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'summary_date');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        return DailyNotificationSummaryResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Display the specified summary.
     */
    public function show(DailyNotificationSummary $summary): DailyNotificationSummaryResource
    {
        return new DailyNotificationSummaryResource($summary);
    }

    /**
     * Get aggregated totals for a date range.
     */
    public function totals(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        $totals = DailyNotificationSummary::getAggregatedTotals($startDate, $endDate);

        return [
            'total_sent' => (int) ($totals['total_sent'] ?? 0),
            'total_success' => (int) ($totals['total_success'] ?? 0),
            'total_failed' => (int) ($totals['total_failed'] ?? 0),
            'success_rate' => (float) ($totals['avg_success_rate'] ?? 0),
        ];
    }

    /**
     * Get breakdown by notification type.
     */
    public function byType(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        return DailyNotificationSummary::getBreakdownByType($startDate, $endDate);
    }

    /**
     * Get breakdown by delivery method.
     */
    public function byDelivery(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        return DailyNotificationSummary::getBreakdownByDelivery($startDate, $endDate);
    }
}
