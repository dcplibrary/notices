<?php

namespace Dcplibrary\Notices\Services;

use Carbon\Carbon;
use Dcplibrary\Notices\Models\DailyNotificationSummary;
use Dcplibrary\Notices\Models\NotificationLog;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationAggregatorService
{
    /**
     * Aggregate notifications for a specific date.
     */
    public function aggregateDate(Carbon $date): array
    {
        Log::info("Aggregating notifications for {$date->format('Y-m-d')}");

        $aggregated = 0;

        try {
            // Get all notification combinations for this date
            $combinations = NotificationLog::whereDate('notification_date', $date)
                ->select('notification_type_id', 'delivery_option_id')
                ->distinct()
                ->get();

            foreach ($combinations as $combo) {
                $this->aggregateCombination(
                    $date,
                    $combo->notification_type_id,
                    $combo->delivery_option_id
                );
                $aggregated++;
            }

            Log::info("Aggregated {$aggregated} combinations for {$date->format('Y-m-d')}");

            return [
                'success' => true,
                'date' => $date->format('Y-m-d'),
                'combinations_aggregated' => $aggregated,
            ];

        } catch (Exception $e) {
            Log::error("Error aggregating date {$date->format('Y-m-d')}", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Aggregate a specific notification type and delivery method combination.
     */
    private function aggregateCombination(Carbon $date, int $notificationTypeId, int $deliveryOptionId): void
    {
        $stats = NotificationLog::whereDate('notification_date', $date)
            ->where('notification_type_id', $notificationTypeId)
            ->where('delivery_option_id', $deliveryOptionId)
            ->selectRaw('
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as total_success,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as total_pending,
                SUM(holds_count) as total_holds,
                SUM(overdues_count) as total_overdues,
                SUM(overdues_2nd_count) as total_overdues_2nd,
                SUM(overdues_3rd_count) as total_overdues_3rd,
                SUM(cancels_count) as total_cancels,
                SUM(recalls_count) as total_recalls,
                SUM(bills_count) as total_bills,
                COUNT(DISTINCT patron_id) as unique_patrons
            ')
            ->first();

        $totalSent = $stats->total_sent ?? 0;
        $totalSuccess = $stats->total_success ?? 0;
        $totalFailed = $stats->total_failed ?? 0;

        $successRate = $totalSent > 0 ? round(($totalSuccess / $totalSent) * 100, 2) : 0;
        $failureRate = $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 2) : 0;

        $dateString = $date->format('Y-m-d');
        $timestamp = now()->toDateTimeString();

        // Use DB::table()->updateOrInsert() to avoid model date casting issues with SQLite
        // The model's 'date' cast converts the string to a Carbon object, which then
        // becomes a datetime in SQLite, breaking the unique constraint lookup
        DB::table('daily_notification_summary')->updateOrInsert(
            [
                'summary_date' => $dateString,
                'notification_type_id' => $notificationTypeId,
                'delivery_option_id' => $deliveryOptionId,
            ],
            [
                'total_sent' => $totalSent,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_pending' => $stats->total_pending ?? 0,
                'total_holds' => $stats->total_holds ?? 0,
                'total_overdues' => $stats->total_overdues ?? 0,
                'total_overdues_2nd' => $stats->total_overdues_2nd ?? 0,
                'total_overdues_3rd' => $stats->total_overdues_3rd ?? 0,
                'total_cancels' => $stats->total_cancels ?? 0,
                'total_recalls' => $stats->total_recalls ?? 0,
                'total_bills' => $stats->total_bills ?? 0,
                'unique_patrons' => $stats->unique_patrons ?? 0,
                'success_rate' => $successRate,
                'failure_rate' => $failureRate,
                'aggregated_at' => $timestamp,
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ]
        );
    }

    /**
     * Aggregate notifications for a date range.
     */
    public function aggregateDateRange(Carbon $startDate, Carbon $endDate): array
    {
        Log::info("Aggregating notifications from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        $totalAggregated = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $result = $this->aggregateDate($currentDate);
            $totalAggregated += $result['combinations_aggregated'];

            $currentDate->addDay();
        }

        Log::info("Completed aggregation for date range", [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_combinations' => $totalAggregated,
        ]);

        return [
            'success' => true,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'combinations_aggregated' => $totalAggregated,
        ];
    }

    /**
     * Aggregate yesterday's data (typical nightly job).
     */
    public function aggregateYesterday(): array
    {
        $yesterday = now()->subDay()->startOfDay();

        return $this->aggregateDate($yesterday);
    }

    /**
     * Re-aggregate all historical data.
     */
    public function reAggregateAll(): array
    {
        Log::info("Re-aggregating all historical data");

        // Get date range from notification_logs
        $firstDate = NotificationLog::min('notification_date');
        $lastDate = NotificationLog::max('notification_date');

        if (!$firstDate || !$lastDate) {
            return [
                'success' => false,
                'message' => 'No notification data found to aggregate',
            ];
        }

        $startDate = Carbon::parse($firstDate)->startOfDay();
        $endDate = Carbon::parse($lastDate)->startOfDay();

        return $this->aggregateDateRange($startDate, $endDate);
    }

    /**
     * Clean up old aggregated data (optional maintenance task).
     */
    public function cleanupOldData(int $keepDays = 365): array
    {
        $cutoffDate = now()->subDays($keepDays);

        Log::info("Cleaning up aggregated data older than {$cutoffDate->format('Y-m-d')}");

        $deleted = DailyNotificationSummary::where('summary_date', '<', $cutoffDate)->delete();

        Log::info("Deleted {$deleted} old summary records");

        return [
            'success' => true,
            'deleted_records' => $deleted,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
        ];
    }
}
