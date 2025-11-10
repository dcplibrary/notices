<?php

namespace Dcplibrary\Notices\Http\Controllers\Api;

use Dcplibrary\Notices\Http\Resources\NotificationLogResource;
use Dcplibrary\Notices\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            $request->input('per_page', config('notices.api.per_page', 20)),
            config('notices.api.max_per_page', 100)
        );

        $query = NotificationLog::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );
        } elseif ($request->has('days')) {
            $query->recent((int) $request->days);
        }

        // Filter by notification type
        if ($request->has('type_id')) {
            $query->ofType((int) $request->type_id);
        }

        // Filter by delivery method
        if ($request->has('delivery_id')) {
            $query->byDeliveryMethod((int) $request->delivery_id);
        }

        // Filter by status
        if ($request->has('status_id')) {
            $query->byStatus((int) $request->status_id);
        }

        // Filter by patron
        if ($request->has('patron_id')) {
            $query->forPatron((int) $request->patron_id);
        }

        // Quick filters
        if ($request->boolean('successful')) {
            $query->successful();
        }

        if ($request->boolean('failed')) {
            $query->failed();
        }

        // Sort
        $sortBy = $request->input('sort_by', 'notification_date');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        return NotificationLogResource::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Display the specified notification.
     */
    public function show(NotificationLog $notification): NotificationLogResource
    {
        return new NotificationLogResource($notification);
    }

    /**
     * Get notification statistics.
     */
    public function stats(Request $request): array
    {
        $query = NotificationLog::query();

        // Apply date filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date)
            );
        } elseif ($request->has('days')) {
            $query->recent((int) $request->days);
        }

        $total = $query->count();
        $successful = (clone $query)->successful()->count();
        $failed = (clone $query)->failed()->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }
}
