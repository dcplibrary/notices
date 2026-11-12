<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for viewing NotificationLog-derived delivery confirmations.
 *
 * Surfaces delivery_confirmations records that originated from the Polaris
 * NotificationLog export (the source of truth for Mail and Email). Supports
 * filtering by date, delivery channel, and notification type.
 */
class NotificationLogViewerController
{
    /**
     * Display NotificationLog-derived delivery confirmations for Mail/Email.
     *
     * Supports optional filters via query parameters:
     * - date:   YYYY-MM-DD notice_date to filter by (string, optional)
     * - channel: 'mail' or 'email' (maps to delivery_option_id 1/2)
     * - type:    notification type shorthand (hold, overdue, overdue1, overdue2,
     *            overdue3, bill, collection, card) mapped to notification_type_id.
     *
     * Results are ordered by notice_date ascending and paginated (200 per page).
     */
    public function __invoke(Request $request): View
    {
        $query = DeliveryConfirmation::query()
            ->where('confirmation_source', 'notification_log')
        ;

        $selectedDate = null;
        $dateParam    = $request->query('date');

        if ($dateParam) {
            try {
                $selectedDate = (new \DateTimeImmutable($dateParam))->format('Y-m-d');

                // Treat the date parameter as either the logical notice_date
                // (when the notifications were logged) OR the export filename
                // date (notification_log_YYYY-MM-DD_*.txt). This avoids the
                // confusion where a 5:00 AM export for the last 24 hours has
                // notice_date values on the previous day.
                $filenamePrefix = 'notification_log_' . $selectedDate . '_';
                $dayStart       = new \DateTimeImmutable($selectedDate . ' 00:00:00');
                $dayEnd         = $dayStart->modify('+1 day');

                $query->where(function ($q) use ($filenamePrefix, $dayStart, $dayEnd) {
                    $q->where('notice_date', '>=', $dayStart)
                        ->where('notice_date', '<', $dayEnd)
                        ->orWhere('source_filename', 'like', $filenamePrefix . '%')
                    ;
                });
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

        // Optional notification type filter via notification_type_id
        // Uses shorthand string codes mapped to known Polaris notification types.
        $type = $request->query('type'); // e.g. 'hold', 'overdue', 'overdue1', 'bill', 'collection', 'card'
        if ($type) {
            $typeMap = [
                'hold'       => [2],          // Hold Pickup
                'overdue'    => [1, 8, 11],   // All overdues
                'overdue1'   => [1],          // 1st Overdue
                'overdue2'   => [8],          // 2nd Overdue
                'overdue3'   => [11],         // 3rd Overdue
                'bill'       => [12],         // Bill / Final Bill
                'collection' => [13],         // Collection Agency
                'card'       => [14],         // Card Expiration
            ];

            $typeIds = $typeMap[$type] ?? null;

            if ($typeIds) {
                $query->whereIn('notification_type_id', $typeIds);
            }
        }

        $logs = $query
            ->orderBy('notice_date')
            ->orderBy('id')
            ->paginate(200)
            ->withQueryString()
        ;

        $filename = $logs->first()?->source_filename ?? null;

        return view('notices::data.notification-log', [
            'logs'         => $logs,
            'selectedDate' => $selectedDate,
            'filename'     => $filename,
            'channel'      => $channel,
            'type'         => $type,
        ]);
    }
}
