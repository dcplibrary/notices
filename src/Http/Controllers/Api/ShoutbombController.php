<?php

namespace Dcplibrary\Notices\Http\Controllers\Api;

use Carbon\Carbon;
use Dcplibrary\Notices\Http\Resources\ShoutbombDeliveryResource;
use Dcplibrary\Notices\Http\Resources\ShoutbombKeywordUsageResource;
use Dcplibrary\Notices\Http\Resources\ShoutbombRegistrationResource;
use Dcplibrary\Notices\Models\ShoutbombDelivery;
use Dcplibrary\Notices\Models\ShoutbombKeywordUsage;
use Dcplibrary\Notices\Models\ShoutbombRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ShoutbombController extends Controller
{
    /**
     * Get Shoutbomb deliveries.
     */
    public function deliveries(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            $request->input('per_page', config('notices.api.per_page', 20)),
            config('notices.api.max_per_page', 100)
        );

        $query = ShoutbombDelivery::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('sent_date', [
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
            ]);
        }

        // Filter by delivery type
        if ($request->has('type')) {
            $query->where('delivery_type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $query->orderBy('sent_date', 'desc');

        return ShoutbombDeliveryResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get keyword usage statistics.
     */
    public function keywordUsage(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            $request->input('per_page', config('notices.api.per_page', 20)),
            config('notices.api.max_per_page', 100)
        );

        $query = ShoutbombKeywordUsage::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('usage_date', [
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
            ]);
        }

        // Filter by keyword
        if ($request->has('keyword')) {
            $query->where('keyword', strtoupper($request->keyword));
        }

        // Sort
        $query->orderBy('usage_date', 'desc');

        return ShoutbombKeywordUsageResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get keyword usage summary.
     */
    public function keywordSummary(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        $summary = ShoutbombKeywordUsage::whereBetween('usage_date', [$startDate, $endDate])
            ->select('keyword', 'keyword_description', DB::raw('SUM(usage_count) as total_usage'))
            ->groupBy('keyword', 'keyword_description')
            ->orderByDesc('total_usage')
            ->get();

        return $summary->toArray();
    }

    /**
     * Get registration snapshots.
     */
    public function registrations(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            $request->input('per_page', config('notices.api.per_page', 20)),
            config('notices.api.max_per_page', 100)
        );

        $query = ShoutbombRegistration::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('snapshot_date', [
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
            ]);
        }

        // Sort
        $query->orderBy('snapshot_date', 'desc');

        return ShoutbombRegistrationResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Get latest registration statistics.
     */
    public function latestRegistration(): ?ShoutbombRegistrationResource
    {
        $latest = ShoutbombRegistration::orderBy('snapshot_date', 'desc')->first();

        return $latest ? new ShoutbombRegistrationResource($latest) : null;
    }

    /**
     * Get delivery statistics.
     */
    public function deliveryStats(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        $stats = ShoutbombDelivery::whereBetween('sent_date', [$startDate, $endDate])
            ->select(
                'delivery_type',
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('delivery_type', 'status')
            ->get()
            ->groupBy('delivery_type')
            ->map(function ($group) {
                return $group->mapWithKeys(function ($item) {
                    return [$item->status => $item->count];
                });
            });

        return $stats->toArray();
    }
}
