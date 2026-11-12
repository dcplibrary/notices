<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for displaying deduplicated hold notifications from the unified
 * notifications table.
 */
class HoldsDedupedController
{
    public function __construct() {}

    /**
     * Show deduplicated holds.
     *
     * Optionally filter by date using `date` query parameter.
     * Without a date filter, shows all notifications (most recent first).
     *
     * @param Request $request incoming request containing an optional `date` query string
     */
    public function __invoke(Request $request): View
    {
        $dateParam    = $request->query('date');
        $selectedDate = null;

        $baseQuery = Notification::query();

        if ($dateParam) {
            try {
                $date         = Carbon::parse($dateParam);
                $selectedDate = $date->toDateString();
                $baseQuery->where('first_exported_at', '>=', $date->copy()->startOfDay())
                    ->where('first_exported_at', '<', $date->copy()->addDay()->startOfDay())
                ;
            } catch (\Throwable $e) {
                // Invalid date, ignore filter
                $selectedDate = null;
            }
        }

        $totalCount    = (clone $baseQuery)->count();
        $combinedCount = (clone $baseQuery)->where('is_combined', true)->count();

        // Server-side sorting for the full deduplicated result set.
        // Default to most recent first when no date filter, oldest first when date filtered
        $defaultDir = $selectedDate ? 'asc' : 'desc';
        $sort       = $request->query('sort', 'first_exported_at');
        $dir        = $request->query('dir', $defaultDir);

        $allowed = [
            'patron_id'          => 'patron_id',
            'item_record_id'     => 'item_record_id',
            'patron_barcode'     => 'patron_barcode',
            'status'             => 'status',
            'delivery_option_id' => 'delivery_option_id',
            'first_exported_at'  => 'first_exported_at',
            'last_exported_at'   => 'last_exported_at',
        ];

        if (!isset($allowed[$sort])) {
            $sort = 'first_exported_at';
        }

        if (!in_array(strtolower($dir), ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $column = $allowed[$sort];
        $baseQuery->orderBy($column, $dir)
            ->orderBy('id', $dir)
        ;

        $notifications = $baseQuery
            ->paginate(200)
            ->withQueryString()
        ;

        return view('notices::notifications.holds-deduped', [
            'notifications' => $notifications,
            'selectedDate'  => $selectedDate,
            'totalCount'    => $totalCount,
            'combinedCount' => $combinedCount,
            'sort'          => $sort,
            'dir'           => strtolower($dir),
        ]);
    }
}
