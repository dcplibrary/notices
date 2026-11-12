<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\PolarisOverdueNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for displaying raw Polaris overdue notification export records.
 *
 * Shows polaris_overdue_notifications rows exactly as they were imported from
 * the Shoutbomb FTP overdue export files. Useful for debugging overdue
 * notification import issues before reconciliation occurs.
 */
class OverdueLogsController
{
    public function __invoke(Request $request): View
    {
        $query = PolarisOverdueNotification::query();

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

        $overdues = $query
            ->orderBy('notification_creation_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(200)
            ->withQueryString()
        ;

        $filename = $overdues->first()?->source_filename ?? null;

        return view('notices::notifications.overdue-logs', [
            'overdues'     => $overdues,
            'selectedDate' => $selectedDate,
            'filename'     => $filename,
        ]);
    }
}
