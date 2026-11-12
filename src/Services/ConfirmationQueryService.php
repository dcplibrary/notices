<?php

namespace Dcplibrary\Notices\Services;

use Carbon\CarbonImmutable;
use Dcplibrary\Notices\Models\DeliveryConfirmation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConfirmationQueryService
{
    /**
     * Get clustered NotificationLog email rows where delivery_string is empty/NULL.
     *
     * This is intended as a diagnostic view for Polaris/LX Starter system/data failures
     * (e.g., clustered blank delivery strings at the same minute).
     *
     * @param string $dateFrom Y-m-d (inclusive)
     * @param string $dateTo   Y-m-d (inclusive)
     *
     * @return Collection Collection of objects:
     *                    - minute_bucket (string, Y-m-d H:i)
     *                    - count (int)
     *                    - first_at (string|null)
     *                    - last_at (string|null)
     */
    public function getEmailBlankDeliveryStringClusters(string $dateFrom, string $dateTo): Collection
    {
        $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
        $rangeEnd   = CarbonImmutable::parse($dateTo)->addDay()->startOfDay();
        $driver     = DB::getDriverName();

        // Cross-DB bucketing by minute.
        // - sqlite: strftime
        // - mysql/mariadb: DATE_FORMAT
        // - pgsql: to_char
        if ('sqlite' === $driver) {
            $bucketExpr = "strftime('%Y-%m-%d %H:%M', notice_date)";
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $bucketExpr = "DATE_FORMAT(notice_date, '%Y-%m-%d %H:%i')";
        } elseif ('pgsql' === $driver) {
            $bucketExpr = "to_char(notice_date, 'YYYY-MM-DD HH24:MI')";
        } else {
            // Fallback: bucket by hour if unknown driver.
            $bucketExpr = 'DATE(notice_date)';
        }

        return DeliveryConfirmation::query()
            ->where('confirmation_source', 'notification_log')
            ->where('delivery_option_id', 2)
            ->where('notice_date', '>=', $rangeStart)
            ->where('notice_date', '<', $rangeEnd)
            ->where(function ($q) {
                $q->whereNull('delivery_string')
                    ->orWhere('delivery_string', '=', '')
                    ->orWhere('delivery_string', '=', 'NULL')
                ;
            })
            ->selectRaw($bucketExpr . ' as minute_bucket')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('MIN(notice_date) as first_at')
            ->selectRaw('MAX(notice_date) as last_at')
            ->groupBy('minute_bucket')
            ->orderByDesc('count')
            ->get()
        ;
    }

    /**
     * Get delivery confirmations for a specific date.
     *
     * @param string $date Date in Y-m-d format
     *
     * @return array Grouped by confirmation source
     */
    public function getDeliveryConfirmations(string $date): array
    {
        return $this->getDateRangeConfirmations($date, $date);
    }

    /**
     * Get delivery confirmations for a date range.
     *
     * @param string $dateFrom Start date in Y-m-d format
     * @param string $dateTo   End date in Y-m-d format
     *
     * @return array Grouped by confirmation source
     */
    public function getDateRangeConfirmations(string $dateFrom, string $dateTo): array
    {
        $cacheKey = sprintf('notices:confirmations:date_range:%s:%s', $dateFrom, $dateTo);

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($dateFrom, $dateTo): array {
            $rangeStart = CarbonImmutable::parse($dateFrom)->startOfDay();
            $rangeEnd   = CarbonImmutable::parse($dateTo)->addDay()->startOfDay();

            $confirmations = DeliveryConfirmation::query()
                ->where('notice_date', '>=', $rangeStart)
                ->where('notice_date', '<', $rangeEnd)
                ->selectRaw('confirmation_source, COUNT(*) as count')
                ->groupBy('confirmation_source')
                ->orderBy('confirmation_source')
                ->get()
                ->keyBy('confirmation_source')
            ;

            return [
                'notification_log'             => $confirmations->get('notification_log')?->count             ?? 0,
                'summary_report'               => $confirmations->get('summary_report')?->count               ?? 0,
                'shoutbomb_voice_failure'      => $confirmations->get('shoutbomb_voice_failure')?->count      ?? 0,
                'shoutbomb_invalid_phone'      => $confirmations->get('shoutbomb_invalid_phone')?->count      ?? 0,
                'shoutbomb_voice_system_error' => $confirmations->get('shoutbomb_voice_system_error')?->count ?? 0,
                'all'                          => $confirmations,
            ];
        });
    }
}
