<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\PolarisNotificationHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for displaying raw Polaris NotificationHistory export records.
 *
 * Shows item-level detail from the Results.Polaris.NotificationHistory table
 * that was imported via FTP. Useful for confirming that Polaris processed a
 * notification before it reached LX Starter or Shoutbomb.
 */
class NotificationHistoryController
{
    /**
     * Display rows from Polaris NotificationHistory exports.
     *
     * Supports optional filters via query parameters:
     * - date:   YYYY-MM-DD notice_date to filter by (string, optional)
     * - channel: 'mail' or 'email' (maps to delivery_option_id 1/2).
     *
     * Results are ordered by notice_date descending and paginated (200 per page).
     */
    public function __invoke(Request $request): View
    {
        $query = PolarisNotificationHistory::query();

        $selectedDate = null;
        $dateParam    = $request->query('date');

        if ($dateParam) {
            try {
                $selectedDate = (new \DateTimeImmutable($dateParam))->format('Y-m-d');
                $dayStart     = new \DateTimeImmutable($selectedDate . ' 00:00:00');
                $query->where('notice_date', '>=', $dayStart)
                    ->where('notice_date', '<', $dayStart->modify('+1 day'))
                ;
            } catch (\Throwable $e) {
                $selectedDate = null;
            }
        }

        // Optional delivery channel filter (mail/email) via delivery_option_id
        $channel = $request->query('channel'); // 'mail', 'email', or null
        if ('mail' === $channel) {
            $query->where('delivery_option_id', 1);
        } elseif ('email' === $channel) {
            $query->where('delivery_option_id', 2);
        }

        $rows = $query
            ->orderBy('notice_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(200)
            ->withQueryString()
        ;

        $filename = null; // filename inferred from date; we don't store it per-row

        return view('notices::data.notification-history', [
            'rows'         => $rows,
            'selectedDate' => $selectedDate,
            'filename'     => $filename,
            'channel'      => $channel,
        ]);
    }
}
