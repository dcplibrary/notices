<?php

namespace Dcplibrary\PolarisNotifications\Services;

use Dcplibrary\PolarisNotifications\Models\PolarisNotificationLog;
use Dcplibrary\PolarisNotifications\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolarisImportService
{
    /**
     * Import notifications from Polaris for a specific date range.
     */
    public function importNotifications(?int $days = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Determine date range
        if ($days !== null) {
            $endDate = now();
            $startDate = now()->subDays($days);
        } elseif (!$startDate || !$endDate) {
            // Default to yesterday's data
            $days = config('polaris-notifications.import.default_days', 1);
            $endDate = now();
            $startDate = now()->subDays($days);
        }

        Log::info("Starting Polaris notification import", [
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ]);

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        try {
            // Get organization ID from config
            $orgId = config('polaris-notifications.reporting_org_id');

            // Query Polaris database
            $query = PolarisNotificationLog::dateRange($startDate, $endDate);

            if ($orgId) {
                $query->forOrganization($orgId);
            }

            $notifications = $query->orderBy('NotificationDateTime')->get();

            Log::info("Found {$notifications->count()} notifications to import");

            $batchSize = config('polaris-notifications.import.batch_size', 500);
            $skipDuplicates = config('polaris-notifications.import.skip_duplicates', true);

            // Process in batches
            foreach ($notifications->chunk($batchSize) as $batch) {
                $records = [];

                foreach ($batch as $notification) {
                    try {
                        // Check if already imported
                        if ($skipDuplicates && $notification->NotificationLogID) {
                            $exists = NotificationLog::where('polaris_log_id', $notification->NotificationLogID)->exists();
                            if ($exists) {
                                $skipped++;
                                continue;
                            }
                        }

                        $records[] = $notification->toLocalFormat();
                        $imported++;
                    } catch (\Exception $e) {
                        Log::error("Error processing notification {$notification->NotificationLogID}", [
                            'error' => $e->getMessage(),
                        ]);
                        $errors++;
                    }
                }

                // Bulk insert the batch
                if (!empty($records)) {
                    NotificationLog::insert($records);
                }
            }

            Log::info("Polaris import completed", [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error("Polaris import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Import full historical data.
     */
    public function importHistorical(Carbon $startDate, ?Carbon $endDate = null): array
    {
        $endDate = $endDate ?? now();

        Log::info("Starting historical import", [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $totalImported = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        // Process month by month to avoid memory issues
        $currentStart = $startDate->copy();

        while ($currentStart->lte($endDate)) {
            $currentEnd = $currentStart->copy()->endOfMonth();
            if ($currentEnd->gt($endDate)) {
                $currentEnd = $endDate->copy();
            }

            Log::info("Importing month: {$currentStart->format('Y-m')}");

            $result = $this->importNotifications(null, $currentStart, $currentEnd);

            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $totalErrors += $result['errors'];

            $currentStart = $currentEnd->copy()->addDay()->startOfDay();
        }

        Log::info("Historical import completed", [
            'total_imported' => $totalImported,
            'total_skipped' => $totalSkipped,
            'total_errors' => $totalErrors,
        ]);

        return [
            'success' => true,
            'imported' => $totalImported,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Get import statistics.
     */
    public function getImportStats(): array
    {
        return [
            'total_records' => NotificationLog::count(),
            'latest_import' => NotificationLog::max('imported_at'),
            'latest_notification' => NotificationLog::max('notification_date'),
            'oldest_notification' => NotificationLog::min('notification_date'),
            'by_type' => NotificationLog::selectRaw('notification_type_id, COUNT(*) as count')
                ->groupBy('notification_type_id')
                ->pluck('count', 'notification_type_id')
                ->toArray(),
            'by_delivery' => NotificationLog::selectRaw('delivery_option_id, COUNT(*) as count')
                ->groupBy('delivery_option_id')
                ->pluck('count', 'delivery_option_id')
                ->toArray(),
        ];
    }

    /**
     * Test Polaris database connection.
     */
    public function testConnection(): array
    {
        try {
            DB::connection('polaris')->getPdo();

            $count = PolarisNotificationLog::count();

            return [
                'success' => true,
                'message' => 'Successfully connected to Polaris database',
                'total_notifications' => $count,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to Polaris database',
                'error' => $e->getMessage(),
            ];
        }
    }
}
