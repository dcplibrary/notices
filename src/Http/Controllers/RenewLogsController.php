<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\PolarisRenewalNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for displaying raw Polaris renewal/almost-due notification export records.
 *
 * Shows polaris_renewal_notifications rows exactly as they were imported from
 * the Shoutbomb FTP renewal export files. Useful for debugging renewal
 * notification import issues before reconciliation occurs.
 */
class RenewLogsController
{
    public function __invoke(Request $request): View
    {
        $query = PolarisRenewalNotification::query();

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

        $renewals = $query
            ->orderBy('notification_creation_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(200)
            ->withQueryString()
        ;

        $filename = $renewals->first()?->source_filename ?? null;

        return view('notices::notifications.renew-logs', [
            'renewals'     => $renewals,
            'selectedDate' => $selectedDate,
            'filename'     => $filename,
        ]);
    }
}
