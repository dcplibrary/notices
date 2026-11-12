<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Models\Notification;
use Illuminate\Support\Facades\Cache;

class NotificationDiagnosticsQueryService
{
    /**
     * Get notification history for a specific patron.
     *
     * @param string $barcode Patron barcode
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPatronNotificationHistory(string $barcode, int $perPage = 50)
    {
        return Notification::where('patron_barcode', $barcode)
            ->with(['deliveryOption', 'importBatch'])
            ->orderBy('first_exported_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
        ;
    }

    /**
     * Get SMS opt-out failures for display on issues page.
     *
     * Returns notifications that failed due to SMS opt-out, grouped by patron.
     *
     * @param int $daysBack How many days back to check (default: 30)
     *
     * @return array Statistics and affected patrons
     */
    public function getSmsOptOutFailures(int $daysBack = 30): array
    {
        $cacheKey = sprintf('notices:sms_opt_out:%d', $daysBack);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($daysBack): array {
            $minDate = now()->subDays($daysBack);

            // Get failed SMS notifications with opt-out message
            $optOuts = Notification::where('delivery_option_id', 8) // SMS
                ->where('status', 'failed')
                ->where('failed_at', '>=', $minDate)
                ->where(function ($query) {
                    $query->where('failure_reason', 'LIKE', '%opted%out%')
                        ->orWhere('failure_reason', 'LIKE', '%OPTED-OUT%')
                    ;
                })
                ->orderBy('failed_at', 'desc')
                ->get()
            ;

            // Group by patron and get most recent for each
            $byPatron = $optOuts->groupBy('patron_barcode')->map(function ($notifications) {
                $latest = $notifications->first();

                return [
                    'patron_id'          => $latest->patron_id,
                    'patron_barcode'     => $latest->patron_barcode,
                    'notification_count' => $notifications->count(),
                    'last_failed_at'     => $latest->failed_at,
                    'failure_reason'     => $latest->failure_reason_formatted,
                ];
            })->values();

            return [
                'total_patrons'  => $byPatron->count(),
                'total_failures' => $optOuts->count(),
                'patrons'        => $byPatron,
            ];
        });
    }
}
