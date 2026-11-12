<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Dcplibrary\Notices\Models\PolarisPhoneNotice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller for displaying raw Polaris PhoneNotices export records.
 *
 * Shows polaris_phone_notices rows (Voice/SMS) that were imported via CSV export.
 * These records are used to cross-reference Shoutbomb exports during reconciliation
 * and serve as the primary source of truth for Voice and SMS delivery attempts.
 */
class PhoneNoticesController
{
    /**
     * Display rows from Polaris PhoneNotices (Voice/SMS) CSV exports.
     *
     * Supports optional filters via query parameters:
     * - date:   YYYY-MM-DD export_datetime to filter by (string, optional)
     * - channel: 'voice' or 'sms' (maps to delivery_option_id 3/8).
     *
     * Results are ordered by export_datetime descending and paginated (200 per page).
     */
    public function __invoke(Request $request): View
    {
        $sortBy  = $request->query('sort', 'export_datetime');
        $sortDir = 'asc' === strtolower($request->query('dir', 'desc')) ? 'asc' : 'desc';

        $query = PolarisPhoneNotice::query();

        $selectedDate = null;
        $dateParam    = $request->query('date');

        if ($dateParam) {
            try {
                $selectedDate = (new \DateTimeImmutable($dateParam))->format('Y-m-d');
                $dayStart     = new \DateTimeImmutable($selectedDate . ' 00:00:00');
                $query->where('export_datetime', '>=', $dayStart)
                    ->where('export_datetime', '<', $dayStart->modify('+1 day'))
                ;
            } catch (\Throwable $e) {
                $selectedDate = null;
            }
        }

        // Optional delivery channel filter (voice/sms) via delivery_option_id
        $channel = $request->query('channel'); // 'voice', 'sms', or null
        if ('voice' === $channel) {
            $query->where('delivery_option_id', 3);
        } elseif ('sms' === $channel) {
            $query->where('delivery_option_id', 8);
        }

        // Server-side sorting across full result set
        $allowedSorts = [
            'patron_last_name'   => 'patron_last_name',
            'patron_barcode'     => 'patron_barcode',
            'patron_phone'       => 'patron_phone',
            'patron_email'       => 'patron_email',
            'site_name'          => 'site_name',
            'item_title'         => 'item_title',
            'delivery_option_id' => 'delivery_option_id',
            'export_datetime'    => 'export_datetime',
        ];
        $sortColumn = $allowedSorts[$sortBy] ?? 'export_datetime';

        $rows = $query
            ->orderBy($sortColumn, $sortDir)
            ->orderBy('id', $sortDir)
            ->paginate(200)
            ->appends($request->query())
        ;

        $filename = null; // Filename is not stored directly; header will just show date filter

        return view('notices::data.phone-notices', [
            'rows'         => $rows,
            'selectedDate' => $selectedDate,
            'filename'     => $filename,
            'channel'      => $channel,
            'sort'         => $sortBy,
            'dir'          => $sortDir,
        ]);
    }
}
