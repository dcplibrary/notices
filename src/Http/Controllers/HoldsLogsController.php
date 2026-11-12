<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\PolarisHoldNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for displaying raw Polaris hold notification logs.
 *
 * Shows the raw polaris_hold_notifications table data (one row per exported notice).
 * This is useful for debugging and auditing the import process.
 */
class HoldsLogsController
{
    /**
     * Show raw polaris_hold_notifications logs.
     *
     * Optionally filter by date using `date` query parameter.
     * Without a date filter, shows all logs (most recent first).
     */
    public function __invoke(Request $request): View
    {
        $query = PolarisHoldNotification::query();

        $selectedDate = null;
        $dateParam    = $request->query('date');

        if ($dateParam) {
            try {
                $selectedDate = Carbon::parse($dateParam)->toDateString();
                $dayStart     = Carbon::parse($selectedDate)->startOfDay();
                $query->where('export_datetime', '>=', $dayStart)
                    ->where('export_datetime', '<', $dayStart->copy()->addDay())
                ;
            } catch (\Throwable $e) {
                $selectedDate = null;
            }
        }

        // Server-side sorting so that sort applies to the full result set,
        // not just the current page in the browser.
        $sort = $request->query('sort', 'notification_creation_date');
        $dir  = $request->query('dir', 'desc');

        $allowed = [
            'patron_id'                  => 'patron_id',
            'item_record_id'             => 'item_record_id',
            'patron_name'                => ['patron_last_name', 'patron_first_name'],
            'patron_barcode'             => 'patron_barcode',
            'title'                      => 'title',
            'delivery_option_id'         => 'delivery_option_id',
            'notification_queue_id'      => 'notification_queue_id',
            'notification_creation_date' => 'notification_creation_date',
        ];

        if (!isset($allowed[$sort])) {
            $sort = 'notification_creation_date';
        }

        if (!in_array(strtolower($dir), ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $column = $allowed[$sort];

        if (is_array($column)) {
            foreach ($column as $col) {
                $query->orderBy($col, $dir);
            }
        } else {
            $query->orderBy($column, $dir);
        }

        $query->orderBy('id', $dir);

        $holds = $query
            ->paginate(200)
            ->withQueryString()
        ;

        return view('notices::notifications.holds-logs', [
            'holds'        => $holds,
            'selectedDate' => $selectedDate,
            'sort'         => $sort,
            'dir'          => strtolower($dir),
        ]);
    }
}
